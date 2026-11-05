<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'academy_subscription_id',
    'academy_id',
    'actor_user_id',
    'event',
    'before',
    'after',
])]
class SubscriptionEvent extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<AcademySubscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(AcademySubscription::class, 'academy_subscription_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
