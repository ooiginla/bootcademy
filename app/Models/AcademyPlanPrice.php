<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $academy_id
 * @property int $plan_id
 * @property int $monthly_price_minor
 * @property int $quarterly_price_minor
 * @property int $annual_price_minor
 * @property int|null $set_by_user_id
 * @property Plan $plan
 */
#[Fillable(['academy_id', 'plan_id', 'monthly_price_minor', 'quarterly_price_minor', 'annual_price_minor', 'set_by_user_id'])]
class AcademyPlanPrice extends Model
{
    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'monthly_price_minor' => 'integer',
            'quarterly_price_minor' => 'integer',
            'annual_price_minor' => 'integer',
        ];
    }
}
