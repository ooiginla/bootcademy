<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Platform-wide online payment settings. There is one row; amounts are minor units.
 *
 * @property int $id
 * @property int $paystack_fee_basis_points
 * @property int $paystack_fee_fixed_minor
 * @property int|null $paystack_fee_cap_minor
 * @property int|null $paystack_fee_waiver_threshold_minor
 * @property int $platform_fee_basis_points
 * @property int $platform_fee_fixed_minor
 */
#[Fillable([
    'paystack_fee_basis_points', 'paystack_fee_fixed_minor', 'paystack_fee_cap_minor',
    'paystack_fee_waiver_threshold_minor', 'platform_fee_basis_points', 'platform_fee_fixed_minor',
])]
class PlatformSetting extends Model
{
    public static function current(): self
    {
        return self::query()->oldest('id')->first() ?? self::query()->create([]);
    }

    protected function casts(): array
    {
        return [
            'paystack_fee_basis_points' => 'integer', 'paystack_fee_fixed_minor' => 'integer',
            'paystack_fee_cap_minor' => 'integer', 'paystack_fee_waiver_threshold_minor' => 'integer',
            'platform_fee_basis_points' => 'integer', 'platform_fee_fixed_minor' => 'integer',
        ];
    }
}
