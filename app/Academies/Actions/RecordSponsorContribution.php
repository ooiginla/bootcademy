<?php

namespace App\Academies\Actions;

use App\Enums\AcademyInvoiceStatus;
use App\Enums\AcademyPaymentStatus;
use App\Models\Academy;
use App\Models\AcademyInvoice;
use App\Models\AcademyPayment;
use App\Models\ScholarshipInvoiceAllocation;
use App\Models\SponsorCommitment;
use App\Models\SponsorContribution;
use App\Models\User;
use App\Platform\AuditLogger;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecordSponsorContribution
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(Academy $academy, ScholarshipInvoiceAllocation $allocation, User $actor, array $data, Request $request): SponsorContribution
    {
        abort_unless($allocation->academy_id === $academy->id, 404);

        return DB::transaction(function () use ($academy, $allocation, $actor, $data, $request): SponsorContribution {
            $allocation = ScholarshipInvoiceAllocation::query()->where('academy_id', $academy->id)->whereKey($allocation->id)
                ->with(['award.scheme', 'award.commitment'])->lockForUpdate()->firstOrFail();
            if ($allocation->funding_type !== 'sponsor' || $allocation->status !== 'active') {
                throw ValidationException::withMessages(['allocation_id' => 'This is not an active sponsor-funded allocation.']);
            }
            $commitment = SponsorCommitment::query()->where('academy_id', $academy->id)
                ->whereKey($allocation->award->sponsor_commitment_id)->lockForUpdate()->firstOrFail();
            $invoice = AcademyInvoice::query()->where('academy_id', $academy->id)
                ->whereKey($allocation->academy_invoice_id)->lockForUpdate()->firstOrFail();
            $amount = Money::toMinor((string) $data['amount']);
            if ($amount > $allocation->amount_minor - $allocation->funded_minor) {
                throw ValidationException::withMessages(['amount' => 'The contribution exceeds the sponsor amount outstanding for this invoice.']);
            }
            if ($amount > $commitment->committed_minor - $commitment->received_minor) {
                throw ValidationException::withMessages(['amount' => 'The contribution exceeds the unpaid sponsor commitment.']);
            }
            if ($amount > $invoice->balanceMinor()) {
                throw ValidationException::withMessages(['amount' => 'The contribution exceeds the invoice balance.']);
            }
            if (($data['external_reference'] ?? null) !== null
                && $academy->payments()->where('external_reference', $data['external_reference'])->exists()) {
                throw ValidationException::withMessages(['external_reference' => 'This payment reference has already been recorded.']);
            }
            do {
                $reference = 'SPN-'.now()->format('Ym').'-'.Str::upper(Str::random(8));
            } while ($academy->payments()->where('reference', $reference)->exists());

            $payment = AcademyPayment::query()->create([
                'academy_id' => $academy->id, 'reference' => $reference,
                'player_id' => $invoice->player_id, 'sponsor_id' => $commitment->sponsor_id,
                'method' => $data['method'], 'status' => AcademyPaymentStatus::Verified,
                'currency' => $invoice->currency, 'amount_minor' => $amount,
                'received_on' => $data['received_on'], 'external_reference' => $data['external_reference'] ?? null,
                'notes' => $data['notes'] ?? null, 'recorded_by_user_id' => $actor->id,
            ]);

            $remaining = $amount;
            foreach ($invoice->installments()->orderBy('sequence')->lockForUpdate()->get() as $installment) {
                if ($remaining === 0) {
                    break;
                }
                $available = $installment->sponsor_responsibility_minor - $installment->sponsor_paid_minor;
                $part = min($remaining, $available);
                if ($part === 0) {
                    continue;
                }
                $payment->allocations()->create([
                    'academy_id' => $academy->id, 'academy_invoice_id' => $invoice->id,
                    'academy_invoice_installment_id' => $installment->id,
                    'scholarship_invoice_allocation_id' => $allocation->id, 'amount_minor' => $part,
                ]);
                $installment->paid_minor += $part;
                $installment->sponsor_paid_minor += $part;
                $installment->save();
                $remaining -= $part;
            }
            if ($remaining !== 0) {
                throw ValidationException::withMessages(['amount' => 'The contribution could not be allocated to the sponsored installments.']);
            }

            $invoice->paid_minor += $amount;
            $invoice->sponsor_paid_minor += $amount;
            $invoice->status = $invoice->balanceMinor() === 0 ? AcademyInvoiceStatus::Settled : AcademyInvoiceStatus::PartiallyPaid;
            $invoice->save();
            $allocation->funded_minor += $amount;
            $allocation->save();
            $commitment->received_minor += $amount;
            $commitment->save();

            $contribution = SponsorContribution::query()->create([
                'academy_id' => $academy->id, 'sponsor_commitment_id' => $commitment->id,
                'scholarship_invoice_allocation_id' => $allocation->id, 'academy_payment_id' => $payment->id,
                'currency' => $invoice->currency, 'amount_minor' => $amount, 'received_on' => $data['received_on'],
                'external_reference' => $data['external_reference'] ?? null, 'notes' => $data['notes'] ?? null,
                'recorded_by_user_id' => $actor->id,
            ]);
            $this->audit->record('academy.sponsor.contribution_recorded', $actor, $academy, $contribution, [
                'sponsor_id' => $commitment->sponsor_id, 'invoice_id' => $invoice->id,
                'amount_minor' => $amount, 'currency' => $invoice->currency,
            ], $request);

            return $contribution;
        });
    }
}
