<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $plan_id
 * @property string $feature_key
 * @property bool $enabled
 * @property int|null $limit
 */
#[Fillable(['plan_id', 'feature_key', 'enabled', 'limit'])]
class PlanFeature extends Model
{
    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'limit' => 'integer'];
    }
}
