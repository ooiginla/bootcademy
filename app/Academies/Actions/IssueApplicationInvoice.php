<?php

namespace App\Academies\Actions;

use App\Enums\AcademyInvoicePurpose;
use App\Enums\AcademyInvoiceStatus;
use App\Enums\ProgrammeCyclePaymentMode;
use App\Models\Academy;
use App\Models\AcademyInvoice;
use App\Models\Application;
use App\Models\ProgrammeCyclePaymentPlan;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IssueApplicationInvoice
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function applicationFee(Academy $academy, Application $application, ?User $actor, Request $request): ?AcademyInvoice
    {
        $offering = $application->programmeCycle()->with(['cycle', 'programme'])->first();
        if ($offering === null || $offering->application_fee_minor === 0) {
            return null;
        }

        return $this->issue($academy, $application, $actor, $request, AcademyInvoicePurpose::Application, [
            ['description' => 'Application fee', 'amount_minor' => $offering->application_fee_minor],
        ]);
    }

    public function programmeFee(Academy $academy, Application $application, ?User $actor, Request $request): ?AcademyInvoice
    {
        $offering = $application->programmeCycle()->with(['cycle', 'programme', 'feeItems', 'installments'])->first();
        if ($offering === null) {
            return null;
        }
        $paymentPlan = $application->programmeCyclePaymentPlan()->with(['items', 'installments'])->first();
        abort_if($paymentPlan !== null && ($paymentPlan->academy_id !== $academy->id
            || $paymentPlan->programme_cycle_id !== $offering->id), 404);
        $items = [];
        if ($paymentPlan !== null) {
            foreach ($paymentPlan->items->sortBy('sort_order') as $item) {
                $items[] = [
                    'description' => $item->name,
                    'amount_minor' => (int) $item->amount_minor,
                ];
            }
        } else {
            foreach ($offering->feeItems->sortBy('sort_order') as $item) {
                if ($item->cohort_id !== null && $item->cohort_id !== $application->cohort_id) {
                    continue;
                }
                $items[] = [
                    'description' => $item->name,
                    'amount_minor' => (int) $item->amount_minor,
                ];
            }
        }
        if (array_sum(array_column($items, 'amount_minor')) === 0) {
            return null;
        }

        return $this->issue(
            $academy,
            $application,
            $actor,
            $request,
            AcademyInvoicePurpose::Programme,
            $items,
            $paymentPlan,
        );
    }

    /** @param list<array{description: string, amount_minor: int}> $items */
    private function issue(
        Academy $academy,
        Application $application,
        ?User $actor,
        Request $request,
        AcademyInvoicePurpose $purpose,
        array $items,
        ?ProgrammeCyclePaymentPlan $paymentPlan = null,
    ): AcademyInvoice {
        $existing = $application->invoices()
            ->where('purpose', $purpose->value)
            ->where('status', '!=', AcademyInvoiceStatus::Void->value)
            ->latest('id')
            ->first();
        if ($existing !== null) {
            return $existing;
        }
        $offering = $application->programmeCycle()->with(['cycle', 'installments'])->firstOrFail();
        do {
            $number = 'INV-'.now()->format('Ym').'-'.Str::upper(Str::random(8));
        } while ($academy->invoices()->where('number', $number)->exists());
        $total = array_sum(array_column($items, 'amount_minor'));
        $invoice = AcademyInvoice::query()->create([
            'academy_id' => $academy->id, 'number' => $number, 'application_id' => $application->id,
            'fee_name' => $purpose === AcademyInvoicePurpose::Application ? 'Application fee' : $offering->fee_name,
            'academy_cycle_id' => $offering->academy_cycle_id, 'programme_cycle_id' => $offering->id,
            'programme_cycle_payment_plan_id' => $paymentPlan?->id,
            'programme_id' => $offering->programme_id, 'branch_id' => $application->branch_id,
            'purpose' => $purpose, 'status' => AcademyInvoiceStatus::Issued,
            'currency' => $offering->currency, 'total_minor' => $total, 'paid_minor' => 0,
            'issued_at' => now(), 'due_on' => $purpose === AcademyInvoicePurpose::Application ? today() : null,
            'created_by_user_id' => $actor?->id,
        ]);
        foreach ($items as $index => $item) {
            $invoice->items()->create([
                'academy_id' => $academy->id, 'description' => $item['description'],
                'amount_minor' => $item['amount_minor'], 'sort_order' => $index,
            ]);
        }
        if ($purpose === AcademyInvoicePurpose::Application) {
            $invoice->installments()->create([
                'academy_id' => $academy->id, 'name' => 'Application fee', 'amount_minor' => $total,
                'paid_minor' => 0, 'due_on' => today(), 'due_offset_days' => null, 'sequence' => 1,
            ]);
        } elseif ($paymentPlan !== null) {
            foreach ($paymentPlan->installments->sortBy('sequence')->values() as $installment) {
                $invoice->installments()->create([
                    'academy_id' => $academy->id, 'name' => $installment->name,
                    'amount_minor' => $installment->amount_minor, 'paid_minor' => 0,
                    'due_on' => $installment->due_on, 'due_offset_days' => $installment->due_offset_days,
                    'sequence' => $installment->sequence,
                ]);
            }
        } elseif ($offering->payment_mode === ProgrammeCyclePaymentMode::Installments
            && (int) $offering->installments->sum('amount_minor') === $total) {
            foreach ($offering->installments->sortBy('sequence')->values() as $installment) {
                $invoice->installments()->create([
                    'academy_id' => $academy->id, 'name' => $installment->name,
                    'amount_minor' => $installment->amount_minor, 'paid_minor' => 0,
                    'due_on' => $installment->due_on, 'due_offset_days' => $installment->due_offset_days,
                    'sequence' => $installment->sequence,
                ]);
            }
        } else {
            $invoice->installments()->create([
                'academy_id' => $academy->id, 'name' => 'Full programme payment', 'amount_minor' => $total,
                'paid_minor' => 0, 'due_on' => $offering->cycle->ends_on,
                'due_offset_days' => $offering->cycle->type->value === 'rolling' ? 0 : null, 'sequence' => 1,
            ]);
        }
        $invoice->due_on = $invoice->installments()->min('due_on');
        $invoice->save();
        $this->audit->record('academy.invoice.issued', $actor, $academy, $invoice, [
            'purpose' => $purpose->value, 'total_minor' => $total, 'currency' => $invoice->currency,
            'application_id' => $application->id,
        ], $request);

        return $invoice;
    }
}
