<?php

namespace App\Academies;

use App\Models\ProgrammeCycle;
use App\Models\ProgrammeCyclePaymentPlan;
use Illuminate\Database\Eloquent\Collection;

class PublicProgrammePaymentPlans
{
    /**
     * Active plans the academy has chosen to show on its public website and application form.
     *
     * @return Collection<int, ProgrammeCyclePaymentPlan>
     */
    public function forOffering(ProgrammeCycle $offering, ?int $cohortId): Collection
    {
        $base = $offering->paymentPlans()
            ->where('term_scope_id', 0)
            ->where('is_active', true)
            ->where('is_public', true);

        $cohortScopeId = (int) ($cohortId ?? 0);
        $scopeId = $cohortScopeId > 0 && (clone $base)->where('cohort_scope_id', $cohortScopeId)->exists()
            ? $cohortScopeId
            : 0;

        return $base->where('cohort_scope_id', $scopeId)
            ->with(['items', 'installments'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
