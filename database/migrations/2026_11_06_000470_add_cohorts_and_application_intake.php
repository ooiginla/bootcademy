<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cohorts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('programme_cycle_id');
            $table->foreignId('branch_id')->nullable();
            $table->string('name', 120);
            $table->string('code', 64)->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('delivery_mode', 24)->default('physical');
            $table->string('status', 24)->default('draft');
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->unique(['academy_id', 'programme_cycle_id', 'name'], 'cohort_offering_name_unique');
            $table->index(['academy_id', 'status', 'starts_on']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'programme_cycle_id'], 'cohort_offering_fk')
                ->references(['academy_id', 'id'])->on('programme_cycles')->restrictOnDelete();
            $table->foreign(['academy_id', 'branch_id'], 'cohort_branch_fk')
                ->references(['academy_id', 'id'])->on('branches')->restrictOnDelete();
        });

        Schema::create('application_intakes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('programme_cycle_id');
            $table->foreignId('cohort_id')->nullable();
            $table->unsignedBigInteger('cohort_scope_id')->default(0);
            $table->foreignId('branch_id')->nullable();
            $table->unsignedBigInteger('branch_scope_id')->default(0);
            $table->date('opens_on')->nullable();
            $table->date('closes_on')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->unique(
                ['academy_id', 'programme_cycle_id', 'cohort_scope_id', 'branch_scope_id'],
                'application_intake_scope_unique',
            );
            $table->index(['academy_id', 'is_enabled', 'opens_on', 'closes_on'], 'application_intake_open_index');
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'programme_cycle_id'], 'intake_offering_fk')
                ->references(['academy_id', 'id'])->on('programme_cycles')->restrictOnDelete();
            $table->foreign(['academy_id', 'cohort_id'], 'intake_cohort_fk')
                ->references(['academy_id', 'id'])->on('cohorts')->restrictOnDelete();
            $table->foreign(['academy_id', 'branch_id'], 'intake_branch_fk')
                ->references(['academy_id', 'id'])->on('branches')->restrictOnDelete();
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->foreignId('cohort_id')->nullable()->after('programme_cycle_id');
            $table->foreignId('application_intake_id')->nullable()->after('cohort_id');
            $table->foreign(['academy_id', 'cohort_id'], 'application_cohort_fk')
                ->references(['academy_id', 'id'])->on('cohorts')->restrictOnDelete();
            $table->foreign(['academy_id', 'application_intake_id'], 'application_intake_fk')
                ->references(['academy_id', 'id'])->on('application_intakes')->restrictOnDelete();
            $table->index(['academy_id', 'cohort_id', 'status']);
        });

        Schema::create('cohort_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('cohort_id');
            $table->foreignId('enrollment_id');
            $table->foreignId('player_id');
            $table->date('joined_on');
            $table->date('left_on')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->unique(['cohort_id', 'enrollment_id'], 'cohort_enrollment_unique');
            $table->index(['academy_id', 'cohort_id', 'status']);
            $table->index(['academy_id', 'player_id', 'status']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'cohort_id'], 'cohort_membership_cohort_fk')
                ->references(['academy_id', 'id'])->on('cohorts')->restrictOnDelete();
            $table->foreign(['academy_id', 'enrollment_id'], 'cohort_membership_enrollment_fk')
                ->references(['academy_id', 'id'])->on('enrollments')->restrictOnDelete();
            $table->foreign(['academy_id', 'player_id'], 'cohort_membership_player_fk')
                ->references(['academy_id', 'id'])->on('players')->restrictOnDelete();
        });

        Schema::table('training_sessions', function (Blueprint $table): void {
            $table->foreignId('cohort_id')->nullable()->after('programme_id');
            $table->foreign(['academy_id', 'cohort_id'], 'session_cohort_fk')
                ->references(['academy_id', 'id'])->on('cohorts')->restrictOnDelete();
            $table->index(['academy_id', 'cohort_id', 'starts_at']);
        });

        $this->backfillDefaultCohorts();
    }

    public function down(): void
    {
        Schema::table('training_sessions', function (Blueprint $table): void {
            $table->dropForeign('session_cohort_fk');
            $table->dropIndex(['academy_id', 'cohort_id', 'starts_at']);
            $table->dropColumn('cohort_id');
        });
        Schema::dropIfExists('cohort_memberships');
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropIndex(['academy_id', 'cohort_id', 'status']);
            $table->dropForeign('application_intake_fk');
            $table->dropForeign('application_cohort_fk');
            $table->dropColumn(['application_intake_id', 'cohort_id']);
        });
        Schema::dropIfExists('application_intakes');
        Schema::dropIfExists('cohorts');
    }

    private function backfillDefaultCohorts(): void
    {
        $timestamp = now();
        $offerings = DB::table('programme_cycles')
            ->join('academy_cycles', function ($join): void {
                $join->on('academy_cycles.id', '=', 'programme_cycles.academy_cycle_id')
                    ->on('academy_cycles.academy_id', '=', 'programme_cycles.academy_id');
            })
            ->select([
                'programme_cycles.id', 'programme_cycles.academy_id', 'programme_cycles.programme_id',
                'programme_cycles.branch_id', 'academy_cycles.starts_on as cycle_starts_on',
                'academy_cycles.ends_on as cycle_ends_on', 'academy_cycles.status as cycle_status',
            ])->orderBy('programme_cycles.id')->get();

        foreach ($offerings as $offering) {
            $earliestEnrollment = DB::table('enrollments')
                ->where('academy_id', $offering->academy_id)
                ->where('programme_cycle_id', $offering->id)
                ->min('starts_on');
            $startsOn = $offering->cycle_starts_on ?? $earliestEnrollment ?? $timestamp->toDateString();
            $status = match ($offering->cycle_status) {
                'active' => 'ongoing',
                'closed' => 'completed',
                default => 'draft',
            };
            $cohortId = DB::table('cohorts')->insertGetId([
                'academy_id' => $offering->academy_id,
                'programme_cycle_id' => $offering->id,
                'branch_id' => $offering->branch_id,
                'name' => 'Default cohort',
                'code' => 'DEFAULT',
                'starts_on' => $startsOn,
                'ends_on' => $offering->cycle_ends_on,
                'capacity' => null,
                'delivery_mode' => 'physical',
                'status' => $status,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            DB::table('applications')
                ->where('academy_id', $offering->academy_id)
                ->where('programme_cycle_id', $offering->id)
                ->whereNull('cohort_id')
                ->update(['cohort_id' => $cohortId, 'updated_at' => $timestamp]);

            $enrollments = DB::table('enrollments')
                ->where('academy_id', $offering->academy_id)
                ->where('programme_cycle_id', $offering->id)
                ->get(['id', 'player_id', 'starts_on', 'ends_on', 'status']);
            foreach ($enrollments as $enrollment) {
                DB::table('cohort_memberships')->insert([
                    'academy_id' => $offering->academy_id,
                    'cohort_id' => $cohortId,
                    'enrollment_id' => $enrollment->id,
                    'player_id' => $enrollment->player_id,
                    'joined_on' => $enrollment->starts_on,
                    'left_on' => $enrollment->ends_on,
                    'status' => in_array($enrollment->status, ['active', 'paused', 'completed', 'withdrawn'], true)
                        ? $enrollment->status
                        : 'active',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }
        }

        $sessions = DB::table('training_sessions')->whereNull('cohort_id')
            ->get(['id', 'academy_id', 'programme_id', 'branch_id', 'all_branches']);
        foreach ($sessions as $session) {
            $candidate = DB::table('cohorts')
                ->join('programme_cycles', function ($join): void {
                    $join->on('programme_cycles.id', '=', 'cohorts.programme_cycle_id')
                        ->on('programme_cycles.academy_id', '=', 'cohorts.academy_id');
                })
                ->join('academy_cycles', function ($join): void {
                    $join->on('academy_cycles.id', '=', 'programme_cycles.academy_cycle_id')
                        ->on('academy_cycles.academy_id', '=', 'programme_cycles.academy_id');
                })
                ->where('cohorts.academy_id', $session->academy_id)
                ->where('programme_cycles.programme_id', $session->programme_id)
                ->where('academy_cycles.status', 'active')
                ->when(
                    (bool) $session->all_branches,
                    fn ($query) => $query->whereNull('programme_cycles.branch_id'),
                    fn ($query) => $query->where(fn ($query) => $query
                        ->where('programme_cycles.branch_id', $session->branch_id)
                        ->orWhereNull('programme_cycles.branch_id'))
                        ->orderByRaw('CASE WHEN programme_cycles.branch_id = ? THEN 0 ELSE 1 END', [$session->branch_id]),
                )
                ->value('cohorts.id');

            if ($candidate !== null) {
                DB::table('training_sessions')->where('id', $session->id)
                    ->update(['cohort_id' => $candidate, 'updated_at' => $timestamp]);
            }
        }
    }
};
