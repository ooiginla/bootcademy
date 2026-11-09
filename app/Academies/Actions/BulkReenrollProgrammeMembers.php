<?php

namespace App\Academies\Actions;

use App\Enums\CohortMembershipStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Academy;
use App\Models\AcademyCycleFee;
use App\Models\AcademyCycleTerm;
use App\Models\Cohort;
use App\Models\CohortMembership;
use App\Models\Enrollment;
use App\Models\EnrollmentTermRegistration;
use App\Models\Programme;
use App\Models\ProgrammeCycle;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BulkReenrollProgrammeMembers
{
    public function __construct(
        private readonly IssueEnrollmentTermProgrammeInvoice $issueProgrammeInvoice,
        private readonly IssuePlayerCycleFeeInvoice $issueCycleFeeInvoice,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Academy $academy, Programme $sourceProgramme, User $actor, array $data, Request $request): int
    {
        abort_unless($sourceProgramme->academy_id === $academy->id, 404);

        return DB::transaction(function () use ($academy, $sourceProgramme, $actor, $data, $request): int {
            $sources = Enrollment::query()->where('academy_id', $academy->id)
                ->where('programme_id', $sourceProgramme->id)
                ->whereIn('id', $data['enrollment_ids'])
                ->lockForUpdate()->get();
            if ($sources->count() !== count(array_unique($data['enrollment_ids']))) {
                throw ValidationException::withMessages(['enrollment_ids' => 'One or more selected enrollments no longer belong to this programme.']);
            }

            $cycleId = (int) $data['academy_cycle_id'];
            $programmeId = (int) $data['programme_id'];
            $term = AcademyCycleTerm::query()->where('academy_id', $academy->id)
                ->where('academy_cycle_id', $cycleId)->whereKey($data['academy_cycle_term_id'])->firstOrFail();
            $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;
            $offering = ProgrammeCycle::query()->where('academy_id', $academy->id)
                ->where('academy_cycle_id', $cycleId)->where('programme_id', $programmeId)
                ->where('is_active', true)
                ->where(function ($query) use ($branchId): void {
                    $query->whereNull('branch_id');
                    if ($branchId !== null) {
                        $query->orWhere('branch_id', $branchId);
                    }
                })
                ->orderByRaw('branch_id IS NULL')->first();
            if ($offering === null) {
                throw ValidationException::withMessages(['programme_id' => 'This programme is not offered for the selected cycle and branch.']);
            }
            $cycle = $offering->cycle()->firstOrFail();
            $startsOn = $term->starts_on ?? $cycle->starts_on ?? today();
            $endsOn = $cycle->type->value === 'rolling' ? $cycle->rollingEndDate($startsOn) : $cycle->ends_on;

            $cohort = null;
            if (isset($data['cohort_id'])) {
                $cohort = Cohort::query()->where('academy_id', $academy->id)
                    ->where('programme_cycle_id', $offering->id)->whereKey($data['cohort_id'])
                    ->lockForUpdate()->firstOrFail();
                if ($cohort->branch_id !== null && $branchId !== $cohort->branch_id) {
                    throw ValidationException::withMessages(['branch_id' => 'The selected cohort belongs to a different branch.']);
                }
            }

            $processed = 0;
            foreach ($sources->groupBy('player_id') as $playerSources) {
                $source = $playerSources->first();
                $target = Enrollment::query()->where('academy_id', $academy->id)
                    ->where('player_id', $source->player_id)->where('programme_id', $programmeId)
                    ->where('academy_cycle_id', $cycleId)->where('branch_id', $branchId)
                    ->lockForUpdate()->first();

                foreach ($playerSources as $selectedSource) {
                    if ($selectedSource->id === $target?->id || $selectedSource->status !== EnrollmentStatus::Active) {
                        continue;
                    }
                    $completedOn = $selectedSource->starts_on->isAfter(today()) ? $selectedSource->starts_on : today();
                    $selectedSource->update(['status' => EnrollmentStatus::Completed, 'ends_on' => $completedOn]);
                    $selectedSource->cohortMemberships()->where('status', CohortMembershipStatus::Active->value)
                        ->update(['status' => CohortMembershipStatus::Completed->value, 'left_on' => $completedOn]);
                }

                if ($target === null) {
                    $target = Enrollment::query()->create([
                        'academy_id' => $academy->id,
                        'player_id' => $source->player_id,
                        'programme_id' => $programmeId,
                        'academy_cycle_id' => $cycleId,
                        'programme_cycle_id' => $offering->id,
                        'branch_id' => $branchId,
                        'uniqueness_key' => implode(':', [$source->player_id, $programmeId, $cycleId, $branchId ?? 0]),
                        'status' => EnrollmentStatus::Active,
                        'starts_on' => $startsOn,
                        'ends_on' => $endsOn,
                    ]);
                } else {
                    $target->update([
                        'programme_cycle_id' => $offering->id,
                        'status' => EnrollmentStatus::Active,
                        'ends_on' => $endsOn,
                    ]);
                }

                $hadRegistration = $target->termRegistrations()->exists();
                $registration = EnrollmentTermRegistration::query()->firstOrCreate(
                    ['enrollment_id' => $target->id, 'academy_cycle_term_id' => $term->id],
                    [
                        'academy_id' => $academy->id,
                        'cohort_id' => $cohort?->id,
                        'registered_at' => now(),
                        'created_by_user_id' => $actor->id,
                    ],
                );
                if (! $registration->wasRecentlyCreated && $registration->cohort_id !== $cohort?->id) {
                    throw ValidationException::withMessages([
                        'cohort_id' => 'This enrollment is already registered for the term under a different cohort scope.',
                    ]);
                }

                if ($cohort !== null) {
                    if ($cohort->capacity !== null) {
                        $isAlreadyMember = $cohort->memberships()->where('enrollment_id', $target->id)->exists();
                        $activeCount = $cohort->memberships()->where('status', CohortMembershipStatus::Active->value)
                            ->lockForUpdate()->get(['id'])->count();
                        if (! $isAlreadyMember && $activeCount >= $cohort->capacity) {
                            throw ValidationException::withMessages(['cohort_id' => "{$cohort->name} has reached its capacity."]);
                        }
                    }
                    $target->cohortMemberships()->where('status', CohortMembershipStatus::Active->value)
                        ->where('cohort_id', '!=', $cohort->id)
                        ->update(['status' => CohortMembershipStatus::Transferred->value, 'left_on' => today()]);
                    CohortMembership::query()->updateOrCreate(
                        ['cohort_id' => $cohort->id, 'enrollment_id' => $target->id],
                        [
                            'academy_id' => $academy->id, 'player_id' => $target->player_id,
                            'joined_on' => $term->starts_on ?? today(), 'left_on' => null,
                            'status' => CohortMembershipStatus::Active,
                        ],
                    );
                }

                if ($registration->wasRecentlyCreated) {
                    $this->issueProgrammeInvoice->handle(
                        $academy, $target, $offering, $registration, ! $hadRegistration, $actor, $request,
                    );
                    $fees = AcademyCycleFee::query()->where('academy_id', $academy->id)
                        ->where('academy_cycle_id', $cycleId)->where('is_active', true)->where('is_compulsory', true)
                        ->where(function ($query) use ($term, $hadRegistration): void {
                            $query->where('academy_cycle_term_id', $term->id);
                            if (! $hadRegistration) {
                                $query->orWhereNull('academy_cycle_term_id');
                            }
                        })
                        ->where(function ($query) use ($cohort): void {
                            $query->whereNull('cohort_id');
                            if ($cohort !== null) {
                                $query->orWhere('cohort_id', $cohort->id);
                            }
                        })
                        ->where(function ($query) use ($programmeId): void {
                            $query->where('applies_to_all_programmes', true)
                                ->orWhereHas('programmes', fn ($programmes) => $programmes->where('programmes.id', $programmeId));
                        })->get();
                    foreach ($fees as $fee) {
                        $this->issueCycleFeeInvoice->handle($academy, $target->player, $fee, $actor, $request);
                    }
                }

                $this->audit->record('academy.enrollment.term_registered', $actor, $academy, $target, [
                    'source_enrollment_id' => $source->id,
                    'academy_cycle_term_id' => $term->id,
                    'cohort_id' => $cohort?->id,
                ], $request);
                $processed++;
            }

            return $processed;
        });
    }
}
