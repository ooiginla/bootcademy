<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academy_id', 'training_session_id', 'player_id', 'status', 'notes', 'marked_by_user_id'])]
class AttendanceRecord extends Model
{
    /** @return BelongsTo<TrainingSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** @return HasMany<AttendanceChangeLog, $this> */
    public function changes(): HasMany
    {
        return $this->hasMany(AttendanceChangeLog::class);
    }
}
