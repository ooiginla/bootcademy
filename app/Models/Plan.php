<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $currency
 * @property int $monthly_price_minor
 * @property int $quarterly_price_minor
 * @property int $annual_price_minor
 * @property int $active_player_limit
 * @property bool $is_active
 * @property int $sort_order
 * @property int $subscriptions_count
 */
#[Fillable([
    'code',
    'name',
    'currency',
    'monthly_price_minor',
    'quarterly_price_minor',
    'annual_price_minor',
    'active_player_limit',
    'is_active',
    'sort_order',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /** @return HasMany<PlanFeature, $this> */
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    /** @return HasMany<AcademySubscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(AcademySubscription::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'monthly_price_minor' => 'integer',
            'quarterly_price_minor' => 'integer',
            'annual_price_minor' => 'integer',
            'active_player_limit' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
