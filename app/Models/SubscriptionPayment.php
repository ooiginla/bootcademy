<?php

namespace App\Models;

use App\Enums\BillingInterval;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * An academy paying the platform for a subscription period.
 *
 * Statuses: pending, verified, failed, expired, review_required.
 *
 * @property int $id
 * @property int $academy_id
 * @property int $plan_id
 * @property BillingInterval $billing_interval
 * @property string $currency
 * @property int $amount_minor
 * @property int $surcharge_minor
 * @property int $platform_fee_minor
 * @property int $paystack_fee_estimate_minor
 * @property int $charged_amount_minor
 * @property int|null $provider_fee_minor
 * @property string $provider
 * @property string $reference
 * @property string|null $access_code
 * @property string|null $subaccount_code
 * @property string $status
 * @property string|null $failure_reason
 * @property int|null $initiated_by_user_id
 * @property Carbon|null $verified_at
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $created_at
 * @property Academy $academy
 * @property Plan $plan
 * @property AcademySubscriptionPeriod|null $period
 */
#[Fillable([
    'academy_id', 'plan_id', 'billing_interval', 'currency', 'amount_minor', 'surcharge_minor', 'platform_fee_minor',
    'paystack_fee_estimate_minor', 'charged_amount_minor', 'provider_fee_minor', 'provider', 'reference', 'access_code',
    'subaccount_code', 'status', 'failure_reason', 'initiated_by_user_id', 'verified_at', 'last_checked_at',
])]
class SubscriptionPayment extends Model
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

    /** @return BelongsTo<User, $this> */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    /** @return HasOne<AcademySubscriptionPeriod, $this> */
    public function period(): HasOne
    {
        return $this->hasOne(AcademySubscriptionPeriod::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'billing_interval' => BillingInterval::class,
            'amount_minor' => 'integer',
            'surcharge_minor' => 'integer',
            'platform_fee_minor' => 'integer',
            'paystack_fee_estimate_minor' => 'integer',
            'charged_amount_minor' => 'integer',
            'provider_fee_minor' => 'integer',
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }
}
