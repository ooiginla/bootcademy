<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['academy_id', 'product_variant_id', 'branch_id', 'type', 'on_hand_delta', 'reserved_delta', 'transfer_reference', 'reference_type', 'reference_id', 'notes', 'created_by_user_id', 'occurred_at'])]
class StockMovement extends Model
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
        return ['on_hand_delta' => 'integer', 'reserved_delta' => 'integer', 'occurred_at' => 'datetime'];
    }
}
