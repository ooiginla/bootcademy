<?php

namespace App\Models;

use App\Enums\CohortStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'academy_id', 'programme_cycle_id', 'branch_id', 'name', 'code', 'starts_on',
    'ends_on', 'capacity', 'delivery_mode', 'status',
])]
class Cohort extends Model
{
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function programmeCycle(): BelongsTo
    {
        return $this->belongsTo(ProgrammeCycle::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CohortMembership::class);
    }

    public function intakes(): HasMany
    {
        return $this->hasMany(ApplicationIntake::class);
    }

    public function trainingSessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'capacity' => 'integer',
            'status' => CohortStatus::class,
        ];
    }
}
