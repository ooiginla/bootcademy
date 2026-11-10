<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $academy_id
 * @property int $programme_cycle_payment_plan_id
 * @property string $name
 * @property int $amount_minor
 * @property int $sort_order
 */
#[Fillable(['academy_id', 'programme_cycle_payment_plan_id', 'name', 'amount_minor', 'sort_order'])]
class ProgrammeCyclePaymentPlanItem extends Model
{
    /** @return BelongsTo<ProgrammeCyclePaymentPlan, $this> */
    public function paymentPlan(): BelongsTo
    {
        return $this->belongsTo(ProgrammeCyclePaymentPlan::class, 'programme_cycle_payment_plan_id');
    }

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'sort_order' => 'integer'];
    }
}
