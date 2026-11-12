<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $academy_id
 * @property int $application_id
 * @property string $reference
 * @property string $status
 * @property string $currency
 * @property int $charged_amount_minor
 * @property int $platform_fee_minor
 * @property int $paystack_fee_estimate_minor
 * @property string|null $subaccount_code
 * @property CarbonImmutable|null $last_checked_at
 * @property-read AcademyPayment|null $payment
 */
#[Fillable([
    'academy_id', 'application_id', 'academy_payment_id', 'provider', 'reference', 'selection_key',
    'status', 'currency', 'base_amount_minor', 'surcharge_minor', 'platform_fee_minor', 'paystack_fee_estimate_minor', 'subaccount_code', 'last_checked_at', 'charged_amount_minor',
    'provider_fee_minor', 'allocation_plan', 'access_code', 'failure_reason', 'initialized_at',
    'verified_at', 'receipt_queued_at',
])]
class OnlinePaymentIntent extends Model
{
    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<AcademyPayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(AcademyPayment::class, 'academy_payment_id');
    }

    protected function casts(): array
    {
        return [
            'base_amount_minor' => 'integer', 'surcharge_minor' => 'integer',
            'platform_fee_minor' => 'integer', 'paystack_fee_estimate_minor' => 'integer', 'last_checked_at' => 'immutable_datetime',
            'charged_amount_minor' => 'integer', 'provider_fee_minor' => 'integer',
            'allocation_plan' => 'array', 'initialized_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime', 'receipt_queued_at' => 'immutable_datetime',
        ];
    }
}
