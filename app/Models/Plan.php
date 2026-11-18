<?php

namespace App\Models;

use App\Enums\BillingInterval;
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
 * @property string|null $description
 * @property int $active_player_limit
 * @property int|null $branch_limit
 * @property int|null $staff_account_limit
 * @property bool $is_recommended
 * @property string|null $migration_support
 * @property string|null $support_level
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
    'description',
    'active_player_limit',
    'branch_limit',
    'staff_account_limit',
    'is_recommended',
    'migration_support',
    'support_level',
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

    /** The plan's list price for an interval, or the academy's own price when the platform set one. */
    public function priceFor(BillingInterval $interval, ?Academy $academy = null): int
    {
        $override = $academy === null ? null : AcademyPlanPrice::query()
            ->where('academy_id', $academy->id)
            ->where('plan_id', $this->id)
            ->first();

        return (int) ($override ?? $this)->getAttribute($interval->priceColumn());
    }

    /** @return array<string, bool> enabled state of every configured plan flag */
    public function flags(): array
    {
        $features = $this->relationLoaded('features') ? $this->features : $this->features()->get();

        return collect(array_keys((array) config('plans.flags')))
            ->mapWithKeys(fn (string $key): array => [$key => $features->firstWhere('feature_key', $key)?->enabled === true])
            ->all();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'monthly_price_minor' => 'integer',
            'quarterly_price_minor' => 'integer',
            'annual_price_minor' => 'integer',
            'active_player_limit' => 'integer',
            'branch_limit' => 'integer',
            'staff_account_limit' => 'integer',
            'is_recommended' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
