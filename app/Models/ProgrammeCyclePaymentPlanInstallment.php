<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $academy_id
 * @property int $programme_cycle_payment_plan_id
 * @property string $name
 * @property int $amount_minor
 * @property Carbon|null $due_on
 * @property int|null $due_offset_days
 * @property int $sequence
 */
#[Fillable([
    'academy_id', 'programme_cycle_payment_plan_id', 'name', 'amount_minor', 'due_on', 'due_offset_days', 'sequence',
])]
class ProgrammeCyclePaymentPlanInstallment extends Model
{
    /** @return BelongsTo<ProgrammeCyclePaymentPlan, $this> */
    public function paymentPlan(): BelongsTo
    {
        return $this->belongsTo(ProgrammeCyclePaymentPlan::class, 'programme_cycle_payment_plan_id');
    }

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'due_on' => 'date', 'due_offset_days' => 'integer', 'sequence' => 'integer'];
    }
}
