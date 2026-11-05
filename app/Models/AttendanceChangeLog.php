<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['academy_id', 'attendance_record_id', 'old_status', 'new_status', 'old_notes', 'new_notes', 'changed_by_user_id', 'created_at'])]
class AttendanceChangeLog extends Model
{
    public $timestamps = false;

    /** @return BelongsTo<AttendanceRecord, $this> */
    public function record(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
    }
}
