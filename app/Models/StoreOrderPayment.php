<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $platform_fee_minor
 * @property int $paystack_fee_estimate_minor
 * @property string|null $subaccount_code
 * @property CarbonImmutable|null $last_checked_at
 */
#[Fillable(['academy_id', 'store_order_id', 'reference', 'method', 'status', 'currency', 'amount_minor', 'surcharge_minor', 'platform_fee_minor', 'paystack_fee_estimate_minor', 'subaccount_code', 'last_checked_at', 'charged_amount_minor', 'provider_fee_minor', 'received_on', 'external_reference', 'access_code', 'failure_reason', 'initialized_at', 'verified_at', 'receipt_queued_at', 'notes', 'recorded_by_user_id'])]
class StoreOrderPayment extends Model
{
    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return BelongsTo<StoreOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'store_order_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer', 'surcharge_minor' => 'integer',
            'platform_fee_minor' => 'integer', 'paystack_fee_estimate_minor' => 'integer', 'last_checked_at' => 'immutable_datetime',
            'charged_amount_minor' => 'integer', 'provider_fee_minor' => 'integer',
            'received_on' => 'date', 'initialized_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime', 'receipt_queued_at' => 'immutable_datetime',
        ];
    }
}
