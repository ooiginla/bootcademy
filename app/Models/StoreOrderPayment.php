<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['academy_id', 'store_order_id', 'reference', 'method', 'status', 'currency', 'amount_minor', 'surcharge_minor', 'charged_amount_minor', 'provider_fee_minor', 'received_on', 'external_reference', 'access_code', 'failure_reason', 'initialized_at', 'verified_at', 'receipt_queued_at', 'notes', 'recorded_by_user_id'])]
class StoreOrderPayment extends Model
{
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'store_order_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer', 'surcharge_minor' => 'integer',
            'charged_amount_minor' => 'integer', 'provider_fee_minor' => 'integer',
            'received_on' => 'date', 'initialized_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime', 'receipt_queued_at' => 'immutable_datetime',
        ];
    }
}
