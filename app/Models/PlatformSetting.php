<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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
 * @property int|null $trial_plan_id
 * @property int|null $post_trial_plan_id
 * @property int $trial_days
 * @property string|null $bank_code
 * @property string|null $bank_name
 * @property string|null $bank_account_name
 * @property string|null $bank_account_number
 * @property string|null $paystack_subaccount_code
 * @property string $paystack_subaccount_status
 * @property string|null $paystack_subaccount_error
 * @property Carbon|null $paystack_subaccount_synced_at
 * @property Plan|null $trialPlan
 * @property Plan|null $postTrialPlan
 */
#[Fillable([
    'paystack_fee_basis_points', 'paystack_fee_fixed_minor', 'paystack_fee_cap_minor',
    'paystack_fee_waiver_threshold_minor', 'platform_fee_basis_points', 'platform_fee_fixed_minor',
    'trial_plan_id', 'post_trial_plan_id', 'trial_days', 'bank_code', 'bank_name', 'bank_account_name',
    'bank_account_number', 'paystack_subaccount_code', 'paystack_subaccount_status', 'paystack_subaccount_error',
    'paystack_subaccount_synced_at',
])]
class PlatformSetting extends Model
{
    public static function current(): self
    {
        return self::query()->oldest('id')->first() ?? self::query()->create([]);
    }

    /** @return BelongsTo<Plan, $this> */
    public function trialPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'trial_plan_id');
    }

    /** @return BelongsTo<Plan, $this> */
    public function postTrialPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'post_trial_plan_id');
    }

    /** True when subscription payments can settle to the platform's own subaccount. */
    public function hasActiveSubaccount(): bool
    {
        return $this->paystack_subaccount_status === 'active' && filled($this->paystack_subaccount_code);
    }

    protected function casts(): array
    {
        return [
            'trial_plan_id' => 'integer', 'post_trial_plan_id' => 'integer', 'trial_days' => 'integer',
            'paystack_subaccount_synced_at' => 'datetime',
            'paystack_fee_basis_points' => 'integer', 'paystack_fee_fixed_minor' => 'integer',
            'paystack_fee_cap_minor' => 'integer', 'paystack_fee_waiver_threshold_minor' => 'integer',
            'platform_fee_basis_points' => 'integer', 'platform_fee_fixed_minor' => 'integer',
        ];
    }
}
