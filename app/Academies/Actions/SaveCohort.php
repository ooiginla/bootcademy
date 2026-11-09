<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\Cohort;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveCohort
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SaveApplicationIntake $saveApplicationIntake,
    ) {}

    public function handle(Academy $academy, User $actor, array $data, Request $request, ?Cohort $cohort = null): Cohort
    {
        $cohort ??= new Cohort(['academy_id' => $academy->id]);
        abort_unless($cohort->academy_id === $academy->id, 404);

        return DB::transaction(function () use ($academy, $actor, $data, $request, $cohort): Cohort {
            $created = ! $cohort->exists;
            $isReceivingApplications = (bool) Arr::pull($data, 'is_receiving_applications');
            $applicationsOpenOn = Arr::pull($data, 'applications_open_on');
            $applicationsCloseOn = Arr::pull($data, 'applications_close_on');
            $offering = $academy->programmeCycles()->findOrFail($data['programme_cycle_id']);
            if ($offering->branch_id !== null) {
                $data['branch_id'] = $offering->branch_id;
            }
            $cohort->fill($data)->save();
            $intake = $academy->applicationIntakes()
                ->where('programme_cycle_id', $offering->id)
                ->where('cohort_id', $cohort->id)
                ->where('branch_scope_id', $cohort->branch_id ?? 0)
                ->first();
            if ($isReceivingApplications || $intake !== null) {
                $this->saveApplicationIntake->handle($academy, $actor, [
                    'programme_cycle_id' => $offering->id,
                    'cohort_id' => $cohort->id,
                    'branch_id' => $cohort->branch_id,
                    'opens_on' => $applicationsOpenOn,
                    'closes_on' => $applicationsCloseOn,
                    'is_enabled' => $isReceivingApplications,
                ], $request, $intake);
            }
            $this->audit->record(
                $created ? 'academy.cohort.created' : 'academy.cohort.updated',
                $actor,
                $academy,
                $cohort,
                ['changed_fields' => array_keys($cohort->getChanges())],
                $request,
            );

            return $cohort;
        });
    }
}
