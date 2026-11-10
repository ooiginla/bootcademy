<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academy_id', 'branch_id', 'number', 'source', 'customer_name', 'customer_email', 'customer_phone', 'member_name', 'delivery_address', 'delivery_method', 'currency', 'subtotal_minor', 'discount_minor', 'delivery_minor', 'total_minor', 'payment_status', 'payment_preference', 'fulfillment_status', 'notes', 'public_token_hash', 'placed_at', 'paid_at', 'fulfilled_at', 'cancelled_at', 'created_by_user_id'])]
class StoreOrder extends Model
{
    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<StoreOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StoreOrderItem::class);
    }

    /** @return HasMany<StoreOrderPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(StoreOrderPayment::class);
    }

    protected function casts(): array
    {
        return ['subtotal_minor' => 'integer', 'discount_minor' => 'integer', 'delivery_minor' => 'integer', 'total_minor' => 'integer', 'placed_at' => 'datetime', 'paid_at' => 'datetime', 'fulfilled_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}
