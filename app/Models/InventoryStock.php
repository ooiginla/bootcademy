<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['academy_id', 'product_variant_id', 'branch_id', 'branch_scope_id', 'on_hand', 'reserved'])]
class InventoryStock extends Model
{
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    protected function casts(): array
    {
        return ['on_hand' => 'integer', 'reserved' => 'integer', 'branch_scope_id' => 'integer'];
    }
}
