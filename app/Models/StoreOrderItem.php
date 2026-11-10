<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academy_id', 'store_order_id', 'product_id', 'product_variant_id', 'product_name', 'variant_name', 'sku', 'unit_price_minor', 'quantity', 'line_total_minor'])]
class StoreOrderItem extends Model
{
    /** @return BelongsTo<StoreOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'store_order_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return HasMany<StoreOrderItemStockAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(StoreOrderItemStockAllocation::class);
    }

    protected function casts(): array
    {
        return ['unit_price_minor' => 'integer', 'quantity' => 'integer', 'line_total_minor' => 'integer'];
    }
}
