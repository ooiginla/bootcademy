<?php

namespace App\Models;

use App\Enums\CohortStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $academy_id
 * @property int $programme_cycle_id
 * @property int|null $branch_id
 * @property string $name
 * @property-read ProgrammeCycle $programmeCycle
 */
#[Fillable([
    'academy_id', 'programme_cycle_id', 'branch_id', 'name', 'code', 'starts_on',
    'ends_on', 'capacity', 'delivery_mode', 'status',
])]
class Cohort extends Model
{
    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return BelongsTo<ProgrammeCycle, $this> */
    public function programmeCycle(): BelongsTo
    {
        return $this->belongsTo(ProgrammeCycle::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<CohortMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(CohortMembership::class);
    }

    /** @return HasMany<ApplicationIntake, $this> */
    public function intakes(): HasMany
    {
        return $this->hasMany(ApplicationIntake::class);
    }

    /** @return HasMany<TrainingSession, $this> */
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
