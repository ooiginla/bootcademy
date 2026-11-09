<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\ApplicationIntake;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaveApplicationIntake
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Academy $academy, User $actor, array $data, Request $request, ?ApplicationIntake $intake = null): ApplicationIntake
    {
        $intake ??= new ApplicationIntake(['academy_id' => $academy->id, 'created_by_user_id' => $actor->id]);
        abort_unless($intake->academy_id === $academy->id, 404);

        return DB::transaction(function () use ($academy, $actor, $data, $request, $intake): ApplicationIntake {
            $created = ! $intake->exists;
            $offering = $academy->programmeCycles()->findOrFail($data['programme_cycle_id']);
            $cohort = isset($data['cohort_id']) ? $academy->cohorts()->findOrFail($data['cohort_id']) : null;
            $data['branch_id'] = $cohort?->branch_id ?? $offering->branch_id ?? ($data['branch_id'] ?? null);
            $data['cohort_scope_id'] = $cohort?->id ?? 0;
            $data['branch_scope_id'] = $data['branch_id'] ?? 0;
            $intake->fill($data)->save();
            $this->audit->record(
                $created ? 'academy.application_intake.created' : 'academy.application_intake.updated',
                $actor,
                $academy,
                $intake,
                ['changed_fields' => array_keys($intake->getChanges())],
                $request,
            );

            return $intake;
        });
    }
}
