<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\AttendanceChangeLog;
use App\Models\AttendanceRecord;
use App\Models\TrainingSession;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveAttendance
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param list<array<string, mixed>> $rows @param list<int> $allowedPlayerIds */
    public function handle(Academy $academy, TrainingSession $session, User $actor, array $rows, array $allowedPlayerIds, Request $request): void
    {
        abort_unless($session->academy_id === $academy->id, 404);
        if ($session->status === 'cancelled') {
            throw ValidationException::withMessages(['attendance' => 'Attendance cannot be recorded for a cancelled session.']);
        }
        $allowed = array_flip($allowedPlayerIds);
        DB::transaction(function () use ($academy, $session, $actor, $rows, $allowed, $request): void {
            $saved = 0;
            $corrected = 0;
            foreach ($rows as $row) {
                $playerId = (int) $row['player_id'];
                if (! isset($allowed[$playerId])) {
                    throw ValidationException::withMessages(['attendance' => 'A player is not eligible for this session roster.']);
                }
                $status = $row['status'] ?? null;
                if (! is_string($status) || $status === '') {
                    continue;
                }
                $notes = trim((string) ($row['notes'] ?? '')) ?: null;
                $record = AttendanceRecord::query()->where(['academy_id' => $academy->id, 'training_session_id' => $session->id, 'player_id' => $playerId])->lockForUpdate()->first();
                if ($record && ($record->status !== $status || $record->notes !== $notes)) {
                    AttendanceChangeLog::query()->create(['academy_id' => $academy->id, 'attendance_record_id' => $record->id, 'old_status' => $record->status, 'new_status' => $status, 'old_notes' => $record->notes, 'new_notes' => $notes, 'changed_by_user_id' => $actor->id, 'created_at' => now()]);
                    $corrected++;
                }
                $record ??= new AttendanceRecord(['academy_id' => $academy->id, 'training_session_id' => $session->id, 'player_id' => $playerId]);
                $record->fill(['status' => $status, 'notes' => $notes, 'marked_by_user_id' => $actor->id])->save();
                $saved++;
            }
            $this->audit->record('academy.attendance.saved', $actor, $academy, $session, ['records_saved' => $saved, 'corrections' => $corrected], $request);
        });
    }
}
