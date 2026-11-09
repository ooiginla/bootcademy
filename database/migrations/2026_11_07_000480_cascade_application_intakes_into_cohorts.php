<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $timestamp = now();
        $intakes = DB::table('application_intakes')->whereNull('cohort_id')->orderBy('id')->get();

        foreach ($intakes as $intake) {
            $cohortId = DB::table('cohorts')
                ->where('academy_id', $intake->academy_id)
                ->where('programme_cycle_id', $intake->programme_cycle_id)
                ->where('branch_id', $intake->branch_id)
                ->value('id');

            if ($cohortId === null) {
                $offering = DB::table('programme_cycles')
                    ->join('academy_cycles', function ($join): void {
                        $join->on('academy_cycles.id', '=', 'programme_cycles.academy_cycle_id')
                            ->on('academy_cycles.academy_id', '=', 'programme_cycles.academy_id');
                    })
                    ->where('programme_cycles.academy_id', $intake->academy_id)
                    ->where('programme_cycles.id', $intake->programme_cycle_id)
                    ->select([
                        'academy_cycles.starts_on', 'academy_cycles.ends_on',
                        'academy_cycles.status as cycle_status',
                    ])->first();

                if ($offering === null) {
                    continue;
                }

                $branchName = $intake->branch_id === null
                    ? null
                    : DB::table('branches')->where('academy_id', $intake->academy_id)
                        ->where('id', $intake->branch_id)->value('name');
                $name = $branchName === null ? 'Default cohort' : 'Default cohort - '.$branchName;
                if (DB::table('cohorts')->where('academy_id', $intake->academy_id)
                    ->where('programme_cycle_id', $intake->programme_cycle_id)->where('name', $name)->exists()) {
                    $name .= ' '.$intake->id;
                }
                $cohortId = DB::table('cohorts')->insertGetId([
                    'academy_id' => $intake->academy_id,
                    'programme_cycle_id' => $intake->programme_cycle_id,
                    'branch_id' => $intake->branch_id,
                    'name' => mb_substr($name, 0, 120),
                    'code' => $intake->branch_id === null ? 'DEFAULT' : 'DEFAULT-'.$intake->branch_id,
                    'starts_on' => $offering->starts_on ?? $timestamp->toDateString(),
                    'ends_on' => $offering->ends_on,
                    'capacity' => null,
                    'delivery_mode' => 'physical',
                    'status' => match ($offering->cycle_status) {
                        'active' => 'ongoing',
                        'closed' => 'completed',
                        default => 'draft',
                    },
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }

            $targetIntakeId = DB::table('application_intakes')
                ->where('academy_id', $intake->academy_id)
                ->where('programme_cycle_id', $intake->programme_cycle_id)
                ->where('cohort_scope_id', $cohortId)
                ->where('branch_scope_id', $intake->branch_scope_id)
                ->where('id', '!=', $intake->id)
                ->value('id');

            if ($targetIntakeId !== null) {
                DB::table('applications')->where('academy_id', $intake->academy_id)
                    ->where('application_intake_id', $intake->id)
                    ->update([
                        'application_intake_id' => $targetIntakeId,
                        'cohort_id' => $cohortId,
                        'updated_at' => $timestamp,
                    ]);
                DB::table('application_intakes')->where('id', $intake->id)
                    ->update(['is_enabled' => false, 'updated_at' => $timestamp]);

                continue;
            }

            DB::table('application_intakes')->where('id', $intake->id)->update([
                'cohort_id' => $cohortId,
                'cohort_scope_id' => $cohortId,
                'updated_at' => $timestamp,
            ]);
            DB::table('applications')->where('academy_id', $intake->academy_id)
                ->where('application_intake_id', $intake->id)
                ->whereNull('cohort_id')
                ->update(['cohort_id' => $cohortId, 'updated_at' => $timestamp]);
        }
    }

    public function down(): void
    {
        // This data normalization is intentionally irreversible because applications
        // must retain their resolved cohort for historical accuracy.
    }
};
