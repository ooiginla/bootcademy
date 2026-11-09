<?php

namespace App\Models;

use App\Enums\CohortMembershipStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['academy_id', 'cohort_id', 'enrollment_id', 'player_id', 'joined_on', 'left_on', 'status'])]
class CohortMembership extends Model
{
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    protected function casts(): array
    {
        return [
            'joined_on' => 'date',
            'left_on' => 'date',
            'status' => CohortMembershipStatus::class,
        ];
    }
}
