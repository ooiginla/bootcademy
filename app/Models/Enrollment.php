<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $academy_id
 * @property int $player_id
 * @property int $programme_id
 * @property int|null $academy_cycle_id
 * @property int|null $programme_cycle_id
 * @property int|null $branch_id
 * @property string|null $uniqueness_key
 * @property EnrollmentStatus $status
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property-read Player $player
 * @property-read Programme $programme
 * @property-read Branch|null $branch
 */
#[Fillable([
    'academy_id', 'player_id', 'programme_id', 'academy_cycle_id', 'programme_cycle_id',
    'branch_id', 'uniqueness_key', 'status', 'starts_on', 'ends_on',
])]
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** @return BelongsTo<Programme, $this> */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /** @return BelongsTo<AcademyCycle, $this> */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AcademyCycle::class, 'academy_cycle_id');
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
    public function cohortMemberships(): HasMany
    {
        return $this->hasMany(CohortMembership::class);
    }

    /** @return HasMany<EnrollmentTermRegistration, $this> */
    public function termRegistrations(): HasMany
    {
        return $this->hasMany(EnrollmentTermRegistration::class);
    }

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'status' => EnrollmentStatus::class];
    }
}
