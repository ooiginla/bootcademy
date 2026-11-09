<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academy_id', 'product_id', 'name', 'sku', 'attributes', 'price_minor', 'is_active'])]
class ProductVariant extends Model
{
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    protected function casts(): array
    {
        return ['attributes' => 'array', 'price_minor' => 'integer', 'is_active' => 'boolean'];
    }
}
