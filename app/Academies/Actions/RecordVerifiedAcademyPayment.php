<?php

namespace App\Academies\Actions;

use App\Enums\AcademyInvoiceStatus;
use App\Enums\AcademyPaymentStatus;
use App\Models\Academy;
use App\Models\AcademyInvoice;
use App\Models\AcademyPayment;
use App\Models\User;
use App\Platform\AuditLogger;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecordVerifiedAcademyPayment
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(Academy $academy, AcademyInvoice $invoice, User $actor, array $data, Request $request): AcademyPayment
    {
        abort_unless($invoice->academy_id === $academy->id, 404);

        return DB::transaction(function () use ($academy, $invoice, $actor, $data, $request): AcademyPayment {
            $invoice = AcademyInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if (in_array($invoice->status, [AcademyInvoiceStatus::Void, AcademyInvoiceStatus::Paid, AcademyInvoiceStatus::WrittenOff], true)) {
                throw ValidationException::withMessages(['amount' => 'This invoice cannot receive another payment.']);
            }
            $amountMinor = Money::toMinor((string) $data['amount']);
            if ($amountMinor > $invoice->balanceMinor()) {
                throw ValidationException::withMessages(['amount' => 'The payment cannot exceed the outstanding invoice balance.']);
            }
            if (($data['external_reference'] ?? null) !== null
                && $academy->payments()->where('external_reference', $data['external_reference'])->exists()) {
                throw ValidationException::withMessages(['external_reference' => 'This payment reference has already been recorded.']);
            }
            do {
                $reference = 'PAY-'.now()->format('Ym').'-'.Str::upper(Str::random(8));
            } while ($academy->payments()->where('reference', $reference)->exists());
            $payment = AcademyPayment::query()->create([
                'academy_id' => $academy->id, 'reference' => $reference,
                'application_id' => $invoice->application_id, 'player_id' => $invoice->player_id,
                'method' => $data['method'], 'status' => AcademyPaymentStatus::Verified,
                'currency' => $invoice->currency, 'amount_minor' => $amountMinor,
                'received_on' => $data['received_on'], 'external_reference' => $data['external_reference'] ?? null,
                'notes' => $data['notes'] ?? null, 'recorded_by_user_id' => $actor->id,
            ]);
            $remaining = $amountMinor;
            foreach ($invoice->installments()->orderBy('sequence')->lockForUpdate()->get() as $installment) {
                if ($remaining === 0) {
                    break;
                }
                $available = $installment->amount_minor - $installment->paid_minor - $installment->written_off_minor;
                $allocated = min($remaining, $available);
                if ($allocated === 0) {
                    continue;
                }
                $payment->allocations()->create([
                    'academy_id' => $academy->id, 'academy_invoice_id' => $invoice->id,
                    'academy_invoice_installment_id' => $installment->id, 'amount_minor' => $allocated,
                ]);
                $installment->increment('paid_minor', $allocated);
                $remaining -= $allocated;
            }
            if ($remaining !== 0) {
                throw ValidationException::withMessages(['amount' => 'The payment could not be fully allocated to this invoice.']);
            }
            $invoice->paid_minor += $amountMinor;
            $invoice->status = $invoice->balanceMinor() === 0
                ? ($invoice->written_off_minor > 0 ? AcademyInvoiceStatus::WrittenOff : AcademyInvoiceStatus::Paid)
                : AcademyInvoiceStatus::PartiallyPaid;
            $invoice->save();
            $this->audit->record('academy.payment.recorded', $actor, $academy, $payment, [
                'invoice_id' => $invoice->id, 'amount_minor' => $amountMinor, 'currency' => $invoice->currency,
                'method' => $payment->method->value,
            ], $request);

            return $payment;
        });
    }
}
