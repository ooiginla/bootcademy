<?php

namespace App\Models;

use App\Enums\BillingInterval;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A span of time an academy has access. Kinds: trial, paid, granted (by the platform admin).
 *
 * @property int $id
 * @property int $academy_id
 * @property int $academy_subscription_id
 * @property int $plan_id
 * @property string $kind
 * @property BillingInterval|null $billing_interval
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property int|null $subscription_payment_id
 * @property int|null $granted_by_user_id
 * @property string|null $note
 * @property Plan $plan
 * @property SubscriptionPayment|null $payment
 */
#[Fillable([
    'academy_id', 'academy_subscription_id', 'plan_id', 'kind', 'billing_interval', 'starts_at', 'ends_at',
    'subscription_payment_id', 'granted_by_user_id', 'note',
])]
class AcademySubscriptionPeriod extends Model
{
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

    /** @return BelongsTo<SubscriptionPayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class, 'subscription_payment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'billing_interval' => BillingInterval::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
