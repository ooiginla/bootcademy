<?php

namespace App\Models;

use App\Enums\AcademyRole;
use App\Enums\MembershipStatus;
use Database\Factories\AcademyMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $academy_id
 * @property int $user_id
 * @property AcademyRole $role
 * @property MembershipStatus $status
 * @property int|null $invited_by_user_id
 * @property Carbon|null $joined_at
 */
#[Fillable(['academy_id', 'user_id', 'role', 'status', 'invited_by_user_id', 'joined_at'])]
class AcademyMembership extends Model
{
    /** @use HasFactory<AcademyMembershipFactory> */
    use HasFactory;

    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Branch, $this> */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'academy_membership_branch')
            ->withPivot('academy_id')->withTimestamps();
    }

    /** @return HasOne<AcademyCoachProfile, $this> */
    public function coachProfile(): HasOne
    {
        return $this->hasOne(AcademyCoachProfile::class, 'membership_id');
    }

    public function canAccessBranch(Branch $branch): bool
    {
        if ($branch->academy_id !== $this->academy_id || $this->status !== MembershipStatus::Active) {
            return false;
        }

        return $this->role === AcademyRole::OwnerAdmin
            || $this->branches()->whereKey($branch->id)->exists();
    }

    public function canManageAcademy(): bool
    {
        return $this->status === MembershipStatus::Active && $this->role->managesAcademy();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => AcademyRole::class,
            'status' => MembershipStatus::class,
            'joined_at' => 'immutable_datetime',
        ];
    }
}
