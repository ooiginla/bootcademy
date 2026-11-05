<?php

namespace App\Models;

use App\Enums\AcademyInvitationStatus;
use App\Enums\AcademyRole;
use Database\Factories\AcademyInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $academy_id
 * @property string $email
 * @property AcademyRole $role
 * @property AcademyInvitationStatus $status
 * @property string $token_hash
 * @property int $invited_by_user_id
 * @property Carbon $expires_at
 * @property int|null $accepted_by_user_id
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 */
#[Fillable([
    'academy_id',
    'email',
    'role',
    'status',
    'token_hash',
    'invited_by_user_id',
    'expires_at',
    'accepted_by_user_id',
    'accepted_at',
    'revoked_at',
])]
class AcademyInvitation extends Model
{
    /** @use HasFactory<AcademyInvitationFactory> */
    use HasFactory;

    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    /** @return BelongsToMany<Branch, $this> */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'academy_invitation_branch')
            ->withPivot('academy_id')->withTimestamps();
    }

    public function isAcceptable(): bool
    {
        return $this->status === AcademyInvitationStatus::Pending
            && $this->expires_at->isFuture();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => AcademyRole::class,
            'status' => AcademyInvitationStatus::class,
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
