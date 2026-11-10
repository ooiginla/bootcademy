<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academy_id', 'academy_payment_id', 'type', 'amount_minor', 'reason', 'effective_on', 'created_by_user_id'])]
class AcademyPaymentAdjustment extends Model
{
    /** @return BelongsTo<AcademyPayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(AcademyPayment::class, 'academy_payment_id');
    }

    /** @return HasMany<AcademyPaymentAdjustmentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(AcademyPaymentAdjustmentAllocation::class);
    }

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'effective_on' => 'date'];
    }
}
