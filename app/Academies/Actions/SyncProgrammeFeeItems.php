<?php

namespace App\Academies\Actions;

use App\Models\ProgrammeCycle;
use App\Models\ProgrammeCyclePaymentPlan;

/**
 * Keeps a programme fee's own fee items in step with the default payment plan of one scope.
 *
 * Prices are set on payment plans. The fee items are a copy of the default plan, kept because
 * term invoices, fee listings and older invoice paths still read them.
 * Call this inside the transaction that changed the plans.
 */
class SyncProgrammeFeeItems
{
    public function handle(ProgrammeCycle $offering, ?int $termId, ?int $cohortId): void
    {
        $plans = $offering->paymentPlans()
            ->where('term_scope_id', (int) ($termId ?? 0))
            ->where('cohort_scope_id', (int) ($cohortId ?? 0));

        $default = (clone $plans)->where('is_default', true)->with('items')->first();
        if ($default === null) {
            $default = (clone $plans)->where('is_active', true)
                ->orderBy('sort_order')->orderBy('id')->with('items')->first();
            $default?->update(['is_default' => true]);
        }

        $offering->feeItems()
            ->where('academy_cycle_term_id', $termId)
            ->where('cohort_id', $cohortId)
            ->delete();

        if (! $default instanceof ProgrammeCyclePaymentPlan) {
            return;
        }

        foreach ($default->items->sortBy('sort_order')->values() as $index => $item) {
            $offering->feeItems()->create([
                'academy_id' => $offering->academy_id,
                'academy_cycle_term_id' => $termId,
                'cohort_id' => $cohortId,
                'name' => $item->name,
                'description' => $item->description,
                'amount_minor' => $item->amount_minor,
                'sort_order' => $index,
            ]);
        }
    }
}
