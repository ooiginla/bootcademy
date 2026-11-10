<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\SaveTrainingSession;
use App\Enums\AcademyRole;
use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\SaveTrainingSessionRequest;
use App\Models\AcademyMembership;
use App\Models\TrainingSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class TrainingSessionController extends Controller
{
    public function index(Request $request, AcademyContext $context): Response
    {
        $membership = $context->membershipOrNull();
        abort_if($membership?->role === AcademyRole::Guardian, 403);
        $academy = $context->academy();
        $branchId = $request->integer('branch_id') ?: null;
        $programmeId = $request->integer('programme_id') ?: null;
        $cohortId = $request->integer('cohort_id') ?: null;
        $coachId = $request->integer('coach_id') ?: null;
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? (string) $request->query('month') : now($academy->timezone)->format('Y-m');
        $monthStart = Carbon::createFromFormat('!Y-m', $month, $academy->timezone)->startOfMonth();
        $calendarStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $calendarEnd = $monthStart->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $query = $academy->trainingSessions()->getQuery()->with(['branches:id,name', 'programme:id,name', 'cohort:id,name', 'coaches.user:id,name'])
            ->when($branchId, fn (Builder $query) => $query->where(fn (Builder $scope) => $scope->where('all_branches', true)->orWhereHas('branches', fn (Builder $branches) => $branches->whereKey($branchId))))
            ->when($programmeId, fn (Builder $query) => $query->where('programme_id', $programmeId))
            ->when($cohortId, fn (Builder $query) => $query->where('cohort_id', $cohortId))
            ->when($coachId, fn (Builder $query) => $query->whereHas('coaches', fn (Builder $coaches) => $coaches->whereKey($coachId)));
        $this->scopeVisibility($query, $membership, $context);

        return Inertia::render('academies/sessions', [
            'sessions' => (clone $query)->orderBy('starts_at')->paginate(25)->withQueryString()->through(fn (TrainingSession $session): array => $this->data($session, $academy->timezone)),
            'calendar_sessions' => (clone $query)->whereBetween('starts_at', [$calendarStart->copy()->utc(), $calendarEnd->copy()->endOfDay()->utc()])->orderBy('starts_at')->get()->map(fn (TrainingSession $session): array => $this->data($session, $academy->timezone)),
            'branches' => $academy->branches()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'programmes' => $academy->programmes()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'cohorts' => $academy->cohorts()->whereNotIn('status', ['completed', 'cancelled'])
                ->with('programmeCycle.programme:id,name')->orderBy('starts_on')->get()
                ->map(fn ($cohort): array => [
                    'id' => $cohort->id, 'name' => $cohort->name,
                    'programme_id' => $cohort->programmeCycle->programme_id,
                    'programme_name' => $cohort->programmeCycle->programme->name,
                ])->values(),
            'coaches' => $academy->memberships()->where('role', AcademyRole::Coach->value)->where('status', MembershipStatus::Active->value)->with('user:id,name')->get()->map(fn (AcademyMembership $item): array => ['id' => $item->id, 'name' => $item->user->name]),
            'filters' => ['branch_id' => $branchId, 'programme_id' => $programmeId, 'cohort_id' => $cohortId, 'coach_id' => $coachId, 'month' => $month],
            'active_tab' => $request->query('tab') === 'calendar' ? 'calendar' : 'list',
            'calendar' => ['start' => $calendarStart->toDateString(), 'end' => $calendarEnd->toDateString(), 'month' => $month],
            'can_manage' => $context->canManageAcademy(),
        ]);
    }

    public function store(SaveTrainingSessionRequest $request, AcademyContext $context, SaveTrainingSession $action): RedirectResponse
    {
        $action->handle($context->academy(), $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Session created.']);

        return back();
    }

    public function update(SaveTrainingSessionRequest $request, TrainingSession $trainingSession, AcademyContext $context, SaveTrainingSession $action): RedirectResponse
    {
        $action->handle($context->academy(), $request->user(), $request->validated(), $request, $trainingSession);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Session updated.']);

        return back();
    }

    /** @param Builder<TrainingSession> $query */
    private function scopeVisibility(Builder $query, ?AcademyMembership $membership, AcademyContext $context): void
    {
        if ($context->isSupportMode() || $membership?->role === AcademyRole::OwnerAdmin) {
            return;
        }
        if ($membership?->role === AcademyRole::Coach) {
            $query->whereHas('coaches', fn (Builder $coaches) => $coaches->whereKey($membership->id));
        }
        if ($membership?->role === AcademyRole::BranchManager) {
            $branchIds = $membership->branches()->pluck('branches.id');
            $query->where(fn (Builder $scope) => $scope->where('all_branches', true)->orWhereHas('branches', fn (Builder $branches) => $branches->whereIn('branches.id', $branchIds)));
        }
    }

    /** @return array<string, mixed> */
    private function data(TrainingSession $session, string $timezone): array
    {
        return [
            'id' => $session->id, 'title' => $session->title, 'all_branches' => $session->all_branches,
            'branch_ids' => $session->branches->pluck('id')->values(), 'branch_names' => $session->branches->pluck('name')->values(),
            'programme_id' => $session->programme_id, 'programme_name' => $session->programme->name,
            'cohort_id' => $session->cohort_id, 'cohort_name' => $session->cohort?->name,
            'coach_membership_ids' => $session->coaches->pluck('id')->values(),
            'coach_names' => $session->coaches->map(fn (AcademyMembership $coach): string => $coach->user->name)->values(),
            'starts_at' => $session->starts_at->setTimezone($timezone)->format('Y-m-d\TH:i'),
            'ends_at' => $session->ends_at->setTimezone($timezone)->format('Y-m-d\TH:i'), 'capacity' => $session->capacity,
            'location' => $session->location, 'delivery_mode' => $session->delivery_mode,
            'meeting_link' => $session->meeting_link, 'notes' => $session->notes, 'status' => $session->status,
        ];
    }
}
