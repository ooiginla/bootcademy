<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\SaveAttendance;
use App\Academies\AttendanceAccess;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\SaveAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Player;
use App\Models\TrainingSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function show(TrainingSession $trainingSession, AcademyContext $context): Response
    {
        abort_unless($trainingSession->academy_id === $context->id(), 404);
        abort_unless(AttendanceAccess::allowed($trainingSession, $context), 403);
        $trainingSession->load(['branches:id,name', 'programme:id,name', 'coaches.user:id,name']);
        $players = $this->roster($trainingSession, $context);
        $records = $trainingSession->attendanceRecords()->withCount('changes')->get()->keyBy('player_id');

        return Inertia::render('academies/attendance', [
            'session' => [
                'id' => $trainingSession->id, 'title' => $trainingSession->title,
                'programme' => $trainingSession->programme->name,
                'branches' => $trainingSession->all_branches ? ['All branches'] : $trainingSession->branches->pluck('name')->values(),
                'coaches' => $trainingSession->coaches->map(fn ($coach): string => $coach->user->name)->values(),
                'starts_at' => $trainingSession->starts_at->setTimezone($context->academy()->timezone)->format('D, j M Y · g:i A'),
                'status' => $trainingSession->status,
            ],
            'roster' => $players->map(function (Player $player) use ($records): array {
                /** @var AttendanceRecord|null $record */
                $record = $records->get($player->id);

                return [
                    'player_id' => $player->id,
                    'name' => collect([$player->first_name, $player->middle_name, $player->last_name])->filter()->implode(' '),
                    'identifier' => $player->internal_identifier,
                    'status' => $record?->status,
                    'notes' => $record?->notes,
                    'corrections' => $record?->changes_count ?? 0,
                ];
            })->values(),
            'summary' => [
                'total' => $players->count(),
                'marked' => $records->whereIn('player_id', $players->pluck('id'))->count(),
                'present' => $records->where('status', 'present')->whereIn('player_id', $players->pluck('id'))->count(),
                'absent' => $records->where('status', 'absent')->whereIn('player_id', $players->pluck('id'))->count(),
                'late' => $records->where('status', 'late')->whereIn('player_id', $players->pluck('id'))->count(),
                'excused' => $records->where('status', 'excused')->whereIn('player_id', $players->pluck('id'))->count(),
            ],
        ]);
    }

    public function update(SaveAttendanceRequest $request, TrainingSession $trainingSession, AcademyContext $context, SaveAttendance $action): RedirectResponse
    {
        $allowedIds = $this->roster($trainingSession, $context)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $rows = $request->validated('attendance');
        abort_unless(is_array($rows), 422);
        $action->handle($context->academy(), $trainingSession, $request->user(), array_values($rows), $allowedIds, $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Attendance saved.']);

        return back();
    }

    /** @return Collection<int, Player> */
    private function roster(TrainingSession $session, AcademyContext $context): Collection
    {
        abort_unless($session->academy_id === $context->id(), 404);
        $sessionDate = $session->starts_at->setTimezone($context->academy()->timezone)->toDateString();
        $branchIds = $session->branches()->pluck('branches.id');

        return Enrollment::query()->where('academy_id', $context->id())->where('programme_id', $session->programme_id)
            ->where('status', EnrollmentStatus::Active->value)->whereDate('starts_on', '<=', $sessionDate)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $sessionDate))
            ->when(! $session->all_branches, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->with('player')->get()->pluck('player')->unique('id')->sortBy(fn (Player $player): string => "$player->last_name $player->first_name")->values();
    }
}
