<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'academy_id', 'coupon_id', 'application_id', 'store_order_id', 'online_payment_intent_id',
    'customer_key', 'currency', 'eligible_amount_minor', 'discount_minor', 'allocations',
    'status', 'applied_at', 'redeemed_at',
])]
class CouponRedemption extends Model
{
    /** @return list<array<string, int>> */
    public function allocationList(): array
    {
        $allocations = $this->getAttribute('allocations');
        if (! is_array($allocations)) {
            return [];
        }

        $result = [];
        foreach ($allocations as $allocation) {
            if (is_array($allocation)) {
                $result[] = $this->integerAllocation($allocation);
            }
        }

        return $result;
    }

    /** @param array<mixed, mixed> $allocation
     * @return array<string, int>
     */
    private function integerAllocation(array $allocation): array
    {
        $result = [];
        foreach ($allocation as $key => $value) {
            if (is_string($key) && is_int($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<StoreOrder, $this> */
    public function storeOrder(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class);
    }

    /** @return BelongsTo<OnlinePaymentIntent, $this> */
    public function onlinePaymentIntent(): BelongsTo
    {
        return $this->belongsTo(OnlinePaymentIntent::class);
    }

    protected function casts(): array
    {
        return [
            'eligible_amount_minor' => 'integer', 'discount_minor' => 'integer',
            'allocations' => 'array', 'applied_at' => 'immutable_datetime', 'redeemed_at' => 'immutable_datetime',
        ];
    }
}
