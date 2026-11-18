<?php

namespace App\Models;

use App\Enums\ReviewAudience;
use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $academy_id
 * @property string $title
 * @property string|null $description
 * @property ReviewAudience $audience
 * @property ReviewStatus $status
 * @property bool $collect_respondent_name
 * @property list<array{id: string, label: string, description: string|null, type: string, required: bool, options: list<string>, max_rating: int}> $fields
 * @property string $public_token
 * @property int $created_by_user_id
 * @property int $responses_count
 * @property string|null $responses_max_submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'academy_id', 'title', 'description', 'audience', 'status', 'collect_respondent_name',
    'fields', 'public_token', 'created_by_user_id',
])]
class Review extends Model
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

    /** @return HasMany<ReviewResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(ReviewResponse::class);
    }

    protected function casts(): array
    {
        return [
            'audience' => ReviewAudience::class,
            'status' => ReviewStatus::class,
            'collect_respondent_name' => 'boolean',
            'fields' => 'array',
        ];
    }
}
