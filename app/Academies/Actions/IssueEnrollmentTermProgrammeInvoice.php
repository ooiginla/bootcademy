<?php

namespace App\Academies\Actions;

use App\Enums\AcademyInvoicePurpose;
use App\Enums\AcademyInvoiceStatus;
use App\Enums\ProgrammeCyclePaymentMode;
use App\Models\Academy;
use App\Models\AcademyInvoice;
use App\Models\Enrollment;
use App\Models\EnrollmentTermRegistration;
use App\Models\ProgrammeCycle;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IssueEnrollmentTermProgrammeInvoice
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(
        Academy $academy,
        Enrollment $enrollment,
        ProgrammeCycle $offering,
        EnrollmentTermRegistration $registration,
        bool $includeGeneralFees,
        User $actor,
        Request $request,
    ): ?AcademyInvoice {
        abort_unless(
            $enrollment->academy_id === $academy->id
            && $offering->academy_id === $academy->id
            && $registration->academy_id === $academy->id,
            404,
        );

        $existing = $academy->invoices()
            ->where('enrollment_term_registration_id', $registration->id)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        // An application invoice predating term registrations already represents the full offering.
        if ($academy->invoices()->where('player_id', $enrollment->player_id)
            ->where('programme_cycle_id', $offering->id)
            ->where('purpose', AcademyInvoicePurpose::Programme->value)
            ->whereNull('enrollment_term_registration_id')->exists()) {
            return null;
        }

        $cohortId = $registration->cohort_id;
        $items = $offering->feeItems()
            ->where(function ($query) use ($registration, $includeGeneralFees): void {
                $query->where('academy_cycle_term_id', $registration->academy_cycle_term_id);
                if ($includeGeneralFees) {
                    $query->orWhereNull('academy_cycle_term_id');
                }
            })
            ->where(function ($query) use ($cohortId): void {
                $query->whereNull('cohort_id');
                if ($cohortId !== null) {
                    $query->orWhere('cohort_id', $cohortId);
                }
            })
            ->orderBy('sort_order')->get();
        $total = (int) $items->sum('amount_minor');
        if ($total === 0) {
            return null;
        }

        do {
            $number = 'INV-'.now()->format('Ym').'-'.Str::upper(Str::random(8));
        } while ($academy->invoices()->where('number', $number)->exists());

        $term = $registration->term;
        $invoice = AcademyInvoice::query()->create([
            'academy_id' => $academy->id,
            'number' => $number,
            'fee_name' => $offering->fee_name.' · '.$term->name,
            'player_id' => $enrollment->player_id,
            'academy_cycle_id' => $offering->academy_cycle_id,
            'programme_cycle_id' => $offering->id,
            'programme_id' => $offering->programme_id,
            'branch_id' => $enrollment->branch_id,
            'enrollment_term_registration_id' => $registration->id,
            'purpose' => AcademyInvoicePurpose::Programme,
            'status' => AcademyInvoiceStatus::Issued,
            'currency' => $offering->currency,
            'total_minor' => $total,
            'paid_minor' => 0,
            'written_off_minor' => 0,
            'issued_at' => now(),
            'due_on' => $term->ends_on ?? $enrollment->ends_on,
            'created_by_user_id' => $actor->id,
        ]);
        foreach ($items->values() as $index => $item) {
            $invoice->items()->create([
                'academy_id' => $academy->id,
                'description' => $item->name,
                'amount_minor' => $item->amount_minor,
                'sort_order' => $index,
            ]);
        }

        $offering->loadMissing(['installments', 'cycle']);
        if ($offering->payment_mode === ProgrammeCyclePaymentMode::Installments
            && (int) $offering->installments->sum('amount_minor') === $total) {
            foreach ($offering->installments->sortBy('sequence') as $installment) {
                $invoice->installments()->create([
                    'academy_id' => $academy->id, 'name' => $installment->name,
                    'amount_minor' => $installment->amount_minor, 'paid_minor' => 0, 'written_off_minor' => 0,
                    'due_on' => $installment->due_on, 'due_offset_days' => $installment->due_offset_days,
                    'sequence' => $installment->sequence,
                ]);
            }
        } else {
            $invoice->installments()->create([
                'academy_id' => $academy->id, 'name' => $term->name.' fees', 'amount_minor' => $total,
                'paid_minor' => 0, 'written_off_minor' => 0,
                'due_on' => $term->ends_on ?? $enrollment->ends_on,
                'due_offset_days' => null, 'sequence' => 1,
            ]);
        }
        $invoice->due_on = $invoice->installments()->min('due_on');
        $invoice->save();

        $this->audit->record('academy.invoice.enrollment_term_issued', $actor, $academy, $invoice, [
            'enrollment_id' => $enrollment->id,
            'term_registration_id' => $registration->id,
            'total_minor' => $total,
        ], $request);

        return $invoice;
    }
}
