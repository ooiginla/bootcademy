<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['academy_id', 'product_category_id', 'name', 'slug', 'sku', 'description', 'currency', 'base_price_minor', 'status', 'track_inventory', 'allow_backorder', 'image_path', 'created_by_user_id'])]
class Product extends Model
{
    use SoftDeletes;

    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return BelongsTo<ProductCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @return BelongsToMany<ProductTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ProductTag::class)->withPivot('academy_id');
    }

    protected function casts(): array
    {
        return ['base_price_minor' => 'integer', 'track_inventory' => 'boolean', 'allow_backorder' => 'boolean'];
    }
}
