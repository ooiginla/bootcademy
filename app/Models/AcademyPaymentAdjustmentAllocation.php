<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['academy_id', 'academy_payment_adjustment_id', 'academy_payment_allocation_id', 'amount_minor'])]
class AcademyPaymentAdjustmentAllocation extends Model
{
    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(AcademyPaymentAdjustment::class, 'academy_payment_adjustment_id');
    }
}
