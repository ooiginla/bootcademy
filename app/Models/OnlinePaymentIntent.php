<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'academy_id', 'application_id', 'academy_payment_id', 'provider', 'reference', 'selection_key',
    'status', 'currency', 'base_amount_minor', 'surcharge_minor', 'charged_amount_minor',
    'provider_fee_minor', 'allocation_plan', 'access_code', 'failure_reason', 'initialized_at',
    'verified_at', 'receipt_queued_at',
])]
class OnlinePaymentIntent extends Model
{
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(AcademyPayment::class, 'academy_payment_id');
    }

    protected function casts(): array
    {
        return [
            'base_amount_minor' => 'integer', 'surcharge_minor' => 'integer',
            'charged_amount_minor' => 'integer', 'provider_fee_minor' => 'integer',
            'allocation_plan' => 'array', 'initialized_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime', 'receipt_queued_at' => 'immutable_datetime',
        ];
    }
}
