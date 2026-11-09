<?php

namespace App\Academies\Actions;

use App\Enums\AcademyInvoiceStatus;
use App\Models\Academy;
use App\Models\AcademyInvoice;
use App\Models\ScholarshipAward;
use App\Models\ScholarshipInvoiceAllocation;
use App\Models\ScholarshipScheme;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplyScholarshipAward
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Academy $academy, ScholarshipAward $award, AcademyInvoice $invoice, User $actor, Request $request): ScholarshipInvoiceAllocation
    {
        abort_unless($award->academy_id === $academy->id && $invoice->academy_id === $academy->id, 404);

        return DB::transaction(function () use ($academy, $award, $invoice, $actor, $request): ScholarshipInvoiceAllocation {
            $award = ScholarshipAward::query()->where('academy_id', $academy->id)->whereKey($award->id)
                ->with(['scheme', 'commitment', 'enrollment'])->lockForUpdate()->firstOrFail();
            $invoice = AcademyInvoice::query()->where('academy_id', $academy->id)->whereKey($invoice->id)
                ->with(['enrollmentTermRegistration', 'cycleFee'])->lockForUpdate()->firstOrFail();
            if ($award->status !== 'active') {
                throw ValidationException::withMessages(['award_id' => 'Only an active scholarship award can be applied.']);
            }
            if (($award->starts_on !== null && $invoice->issued_at->toDateString() < $award->starts_on->toDateString())
                || ($award->ends_on !== null && $invoice->issued_at->toDateString() > $award->ends_on->toDateString())) {
                throw ValidationException::withMessages(['invoice_id' => 'The invoice falls outside this beneficiary award period.']);
            }
            if ($invoice->player_id !== $award->player_id) {
                throw ValidationException::withMessages(['invoice_id' => 'This invoice belongs to another beneficiary.']);
            }
            if ($award->enrollment !== null && ($award->enrollment->academy_cycle_id !== $invoice->academy_cycle_id
                || $award->enrollment->programme_id !== $invoice->programme_id
                || $award->enrollment->branch_id !== $invoice->branch_id)) {
                throw ValidationException::withMessages(['invoice_id' => 'This invoice is outside the enrollment selected for the award.']);
            }
            if (in_array($invoice->status, [AcademyInvoiceStatus::Void, AcademyInvoiceStatus::WrittenOff, AcademyInvoiceStatus::Paid, AcademyInvoiceStatus::Settled], true)) {
                throw ValidationException::withMessages(['invoice_id' => 'This invoice can no longer receive a scholarship allocation.']);
            }
            $existing = ScholarshipInvoiceAllocation::query()->where('scholarship_award_id', $award->id)
                ->where('academy_invoice_id', $invoice->id)->first();
            if ($existing !== null) {
                return $existing;
            }

            $scheme = $award->scheme;
            $this->assertScope($scheme, $invoice);
            $available = $invoice->personalBalanceMinor();
            if ($available === 0) {
                throw ValidationException::withMessages(['invoice_id' => 'The beneficiary has no personal balance left on this invoice.']);
            }
            $amount = match ($scheme->coverage_type) {
                'full' => $available,
                'percentage' => min($available, (int) round($invoice->total_minor * $scheme->percentage_basis_points / 10000)),
                'fixed' => min($available, (int) $scheme->fixed_amount_minor),
                default => 0,
            };
            if ($amount === 0) {
                throw ValidationException::withMessages(['invoice_id' => 'The scholarship calculates to a zero allocation for this invoice.']);
            }

            if ($scheme->funding_type === 'sponsor') {
                $commitment = $award->commitment;
                if ($commitment === null || $commitment->sponsor_id !== $scheme->sponsor_id || $commitment->status !== 'active') {
                    throw ValidationException::withMessages(['award_id' => 'This sponsored award needs an active commitment from the scheme sponsor.']);
                }
                if ($commitment->academy_cycle_id !== null && $commitment->academy_cycle_id !== $invoice->academy_cycle_id) {
                    throw ValidationException::withMessages(['invoice_id' => 'The sponsor commitment does not cover this invoice cycle.']);
                }
                if (($commitment->starts_on !== null && $invoice->issued_at->toDateString() < $commitment->starts_on->toDateString())
                    || ($commitment->ends_on !== null && $invoice->issued_at->toDateString() > $commitment->ends_on->toDateString())) {
                    throw ValidationException::withMessages(['invoice_id' => 'The invoice falls outside the sponsor commitment period.']);
                }
                $allocated = ScholarshipInvoiceAllocation::query()
                    ->whereHas('award', fn ($query) => $query->where('sponsor_commitment_id', $commitment->id))
                    ->where('status', 'active')->sum('amount_minor');
                if ($amount > $commitment->committed_minor - $allocated) {
                    throw ValidationException::withMessages(['invoice_id' => 'The sponsor commitment does not have enough unallocated value.']);
                }
            }

            $allocation = ScholarshipInvoiceAllocation::query()->create([
                'academy_id' => $academy->id, 'scholarship_award_id' => $award->id,
                'academy_invoice_id' => $invoice->id, 'funding_type' => $scheme->funding_type,
                'amount_minor' => $amount, 'funded_minor' => $scheme->funding_type === 'academy' ? $amount : 0,
                'status' => 'active', 'created_by_user_id' => $actor->id,
            ]);

            $remaining = $amount;
            foreach ($invoice->installments()->orderBy('sequence')->lockForUpdate()->get() as $installment) {
                if ($remaining === 0) {
                    break;
                }
                $studentPaid = $installment->paid_minor - $installment->sponsor_paid_minor;
                $personal = max(0, $installment->amount_minor - $studentPaid - $installment->written_off_minor
                    - $installment->scholarship_concession_minor - $installment->sponsor_responsibility_minor);
                $part = min($remaining, $personal);
                if ($part === 0) {
                    continue;
                }
                if ($scheme->funding_type === 'academy') {
                    $installment->scholarship_concession_minor += $part;
                } else {
                    $installment->sponsor_responsibility_minor += $part;
                }
                $installment->save();
                $remaining -= $part;
            }
            if ($remaining !== 0) {
                throw ValidationException::withMessages(['invoice_id' => 'The scholarship could not be allocated across the invoice installments.']);
            }
            if ($scheme->funding_type === 'academy') {
                $invoice->scholarship_concession_minor += $amount;
                if ($invoice->balanceMinor() === 0) {
                    $invoice->status = AcademyInvoiceStatus::Settled;
                }
            } else {
                $invoice->sponsor_responsibility_minor += $amount;
            }
            $invoice->save();

            $this->audit->record('academy.scholarship.applied', $actor, $academy, $allocation, [
                'award_id' => $award->id, 'invoice_id' => $invoice->id,
                'funding_type' => $scheme->funding_type, 'amount_minor' => $amount,
            ], $request);

            return $allocation;
        });
    }

    private function assertScope(ScholarshipScheme $scheme, AcademyInvoice $invoice): void
    {
        if ($scheme->academy_cycle_id !== null && $scheme->academy_cycle_id !== $invoice->academy_cycle_id) {
            throw ValidationException::withMessages(['invoice_id' => 'The scholarship does not cover this cycle.']);
        }
        if ($scheme->programme_id !== null && $scheme->programme_id !== $invoice->programme_id) {
            throw ValidationException::withMessages(['invoice_id' => 'The scholarship does not cover this programme.']);
        }
        if ($scheme->branch_id !== null && $scheme->branch_id !== $invoice->branch_id) {
            throw ValidationException::withMessages(['invoice_id' => 'The scholarship does not cover this branch.']);
        }
        if ($scheme->academy_cycle_term_id !== null) {
            $termId = $invoice->enrollmentTermRegistration?->academy_cycle_term_id ?? $invoice->cycleFee?->academy_cycle_term_id;
            if ($termId !== $scheme->academy_cycle_term_id) {
                throw ValidationException::withMessages(['invoice_id' => 'The scholarship does not cover this term.']);
            }
        }
        if (($scheme->starts_on !== null && $invoice->issued_at->toDateString() < $scheme->starts_on->toDateString())
            || ($scheme->ends_on !== null && $invoice->issued_at->toDateString() > $scheme->ends_on->toDateString())) {
            throw ValidationException::withMessages(['invoice_id' => 'The invoice falls outside the scholarship validity dates.']);
        }
    }
}
