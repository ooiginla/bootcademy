<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $academy_id
 * @property int $branch_id
 * @property int $programme_id
 * @property bool $all_branches
 * @property int|null $coach_membership_id
 * @property string $title
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property int|null $capacity
 * @property string|null $location
 * @property string $delivery_mode
 * @property string|null $meeting_link
 * @property string|null $notes
 * @property string $status
 */
#[Fillable(['academy_id', 'branch_id', 'programme_id', 'cohort_id', 'all_branches', 'coach_membership_id', 'title', 'starts_at', 'ends_at', 'capacity', 'location', 'delivery_mode', 'meeting_link', 'notes', 'status', 'created_by_user_id'])]
class TrainingSession extends Model
{
    use SoftDeletes;

    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Programme, $this> */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /** @return BelongsTo<Cohort, $this> */
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    /** @return BelongsTo<AcademyMembership, $this> */
    public function coachMembership(): BelongsTo
    {
        return $this->belongsTo(AcademyMembership::class, 'coach_membership_id');
    }

    /** @return BelongsToMany<Branch, $this> */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'training_session_branch')->withPivot('academy_id')->withTimestamps();
    }

    /** @return BelongsToMany<AcademyMembership, $this> */
    public function coaches(): BelongsToMany
    {
        return $this->belongsToMany(AcademyMembership::class, 'training_session_coach')->withPivot('academy_id')->withTimestamps();
    }

    /** @return HasMany<AttendanceRecord, $this> */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'capacity' => 'integer', 'all_branches' => 'boolean'];
    }
}
