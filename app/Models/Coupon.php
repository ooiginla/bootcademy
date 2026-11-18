<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'academy_id', 'code', 'name', 'discount_type', 'percentage_basis_points', 'fixed_amount_minor',
    'currency', 'applies_to', 'minimum_amount_minor', 'starts_on', 'ends_on', 'max_redemptions',
    'per_customer_limit', 'is_active', 'created_by_user_id',
])]
class Coupon extends Model
{
    /** @return list<string> */
    public function scopes(): array
    {
        $scopes = $this->getAttribute('applies_to');

        return is_array($scopes)
            ? array_values(array_filter($scopes, is_string(...)))
            : [];
    }

    public function startsOnDate(): ?CarbonInterface
    {
        $date = $this->getAttribute('starts_on');

        return $date instanceof CarbonInterface ? $date : null;
    }

    public function endsOnDate(): ?CarbonInterface
    {
        $date = $this->getAttribute('ends_on');

        return $date instanceof CarbonInterface ? $date : null;
    }

    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<CouponRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    protected function casts(): array
    {
        return [
            'percentage_basis_points' => 'integer', 'fixed_amount_minor' => 'integer',
            'minimum_amount_minor' => 'integer', 'applies_to' => 'array',
            'starts_on' => 'date', 'ends_on' => 'date', 'max_redemptions' => 'integer',
            'per_customer_limit' => 'integer', 'is_active' => 'boolean',
        ];
    }
}
