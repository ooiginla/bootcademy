<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\Enrollment;
use App\Models\Player;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SaveEnrollment
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(
        Academy $academy,
        Player $player,
        User $actor,
        array $data,
        Request $request,
        ?Enrollment $enrollment = null,
    ): Enrollment {
        abort_unless($player->academy_id === $academy->id, 404);
        $enrollment ??= new Enrollment(['academy_id' => $academy->id, 'player_id' => $player->id]);
        abort_unless($enrollment->academy_id === $academy->id && $enrollment->player_id === $player->id, 404);
        $wasExisting = $enrollment->exists;
        $programmeId = (int) $data['programme_id'];
        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;
        $cycleId = isset($data['academy_cycle_id'])
            ? (int) $data['academy_cycle_id']
            : $enrollment->academy_cycle_id;
        $scopeChanged = ! $wasExisting
            || $enrollment->programme_id !== $programmeId
            || $enrollment->branch_id !== $branchId
            || $enrollment->academy_cycle_id !== $cycleId;

        if ($scopeChanged && $player->enrollments()
            ->where('programme_id', $programmeId)
            ->where('academy_cycle_id', $cycleId)
            ->where('branch_id', $branchId)
            ->when($wasExisting, fn ($query) => $query->where('id', '!=', $enrollment->id))
            ->exists()) {
            throw ValidationException::withMessages([
                'programme_id' => 'This player is already enrolled in that programme and branch for the same cycle.',
            ]);
        }

        if ($scopeChanged && $cycleId !== null) {
            $offering = $academy->programmeCycles()
                ->where('academy_cycle_id', $cycleId)
                ->where('programme_id', $programmeId)
                ->where(function ($query) use ($branchId): void {
                    $query->where('branch_id', $branchId)->orWhereNull('branch_id');
                })
                ->orderByRaw('branch_id IS NULL')
                ->first();
            $data['programme_cycle_id'] = $offering?->id;
        }

        $enrollment->fill($data);
        if ($scopeChanged || $enrollment->uniqueness_key !== null) {
            $enrollment->uniqueness_key = implode(':', [$player->id, $programmeId, $cycleId ?? 0, $branchId ?? 0]);
        }
        try {
            $enrollment->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'programme_id' => 'This player is already enrolled in that programme and branch for the same cycle.',
            ]);
        }

        $this->audit->record(
            $wasExisting ? 'academy.enrollment.updated' : 'academy.enrollment.created',
            $actor,
            $academy,
            $enrollment,
            ['changed_fields' => array_keys($enrollment->getChanges())],
            $request,
        );

        return $enrollment;
    }
}
