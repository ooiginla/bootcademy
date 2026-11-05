<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Database\Factories\AcademySubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $academy_id
 * @property int $plan_id
 * @property SubscriptionStatus $status
 * @property Carbon $starts_at
 * @property int|null $assigned_by_user_id
 * @property Academy $academy
 * @property Plan $plan
 */
#[Fillable(['academy_id', 'plan_id', 'status', 'starts_at', 'assigned_by_user_id'])]
class AcademySubscription extends Model
{
    /** @use HasFactory<AcademySubscriptionFactory> */
    use HasFactory;

    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return HasMany<SubscriptionEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'immutable_datetime',
        ];
    }
}
