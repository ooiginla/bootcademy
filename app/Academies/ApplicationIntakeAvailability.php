<?php

namespace App\Academies;

use App\Enums\AcademyCycleStatus;
use App\Enums\CohortStatus;
use App\Models\Academy;
use App\Models\ApplicationIntake;
use Illuminate\Database\Eloquent\Builder;

class ApplicationIntakeAvailability
{
    /** @return Builder<ApplicationIntake> */
    public function query(Academy $academy): Builder
    {
        $today = now($academy->timezone)->toDateString();

        return $academy->applicationIntakes()->getQuery()
            ->openOn($today)
            ->whereNotNull('cohort_id')
            ->whereHas('programmeCycle', fn (Builder $query) => $query
                ->where('is_active', true)
                ->whereHas('cycle', fn (Builder $query) => $query->where('status', AcademyCycleStatus::Active->value)))
            ->whereHas('cohort', fn (Builder $query) => $query
                ->whereNotIn('status', [
                    CohortStatus::Completed->value,
                    CohortStatus::Cancelled->value,
                ])
                ->where(fn (Builder $query) => $query
                    ->whereNull('capacity')
                    ->orWhereRaw('(select count(*) from cohort_memberships where cohort_memberships.cohort_id = cohorts.id and cohort_memberships.status = ?) < cohorts.capacity', ['active'])));
    }
}
