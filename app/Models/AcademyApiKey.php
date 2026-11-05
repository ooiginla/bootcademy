<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $academy_id
 * @property int|null $created_by_user_id
 * @property string $name
 * @property string $prefix
 * @property string $secret_hash
 * @property Carbon|null $last_used_at
 * @property Carbon|null $revoked_at
 */
#[Fillable(['academy_id', 'created_by_user_id', 'name', 'prefix', 'secret_hash', 'last_used_at', 'revoked_at'])]
class AcademyApiKey extends Model
{
    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    protected function casts(): array
    {
        return ['last_used_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }
}
