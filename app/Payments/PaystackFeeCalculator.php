<?php

namespace App\Payments;

use App\Models\Academy;
use App\Models\PlatformSetting;

/**
 * Works out what the payer is charged on top of the amount the academy receives.
 *
 * The academy's subaccount gets exactly the base amount. The surcharge goes to the platform's
 * main account, which pays Paystack's fee from it; what remains is the platform fee.
 * The surcharge is grossed up so that, at the configured Paystack rate, it covers both.
 */
class PaystackFeeCalculator
{
    /** @return array{platform_fee: int, paystack_fee: int, surcharge: int} */
    public function breakdown(Academy $academy, int $baseMinor): array
    {
        if ($baseMinor <= 0) {
            return ['platform_fee' => 0, 'paystack_fee' => 0, 'surcharge' => 0];
        }
        $rule = $this->rule($academy);
        $platformFee = (int) round($baseMinor * $rule['platform_basis_points'] / 10000) + $rule['platform_fixed_minor'];
        $paystackFee = $this->paystackFee($rule, $baseMinor + $platformFee);

        return ['platform_fee' => $platformFee, 'paystack_fee' => $paystackFee, 'surcharge' => $platformFee + $paystackFee];
    }

    public function surcharge(Academy $academy, int $baseMinor): int
    {
        return $this->breakdown($academy, $baseMinor)['surcharge'];
    }

    /**
     * The fee settings that apply to an academy: platform Paystack rates plus the platform fee,
     * with the academy's own platform fee override when one is set.
     *
     * @return array{basis_points: int, fixed_minor: int, cap_minor: int|null, waiver_threshold_minor: int|null, platform_basis_points: int, platform_fixed_minor: int}
     */
    public function rule(Academy $academy): array
    {
        $settings = PlatformSetting::current();
        $hasOverride = $academy->platform_fee_basis_points !== null || $academy->platform_fee_fixed_minor !== null;

        return [
            'basis_points' => $settings->paystack_fee_basis_points,
            'fixed_minor' => $settings->paystack_fee_fixed_minor,
            'cap_minor' => $settings->paystack_fee_cap_minor,
            'waiver_threshold_minor' => $settings->paystack_fee_waiver_threshold_minor,
            'platform_basis_points' => $hasOverride ? (int) $academy->platform_fee_basis_points : $settings->platform_fee_basis_points,
            'platform_fixed_minor' => $hasOverride ? (int) $academy->platform_fee_fixed_minor : $settings->platform_fee_fixed_minor,
        ];
    }

    /**
     * Paystack's fee on a charge that must leave $netMinor after the fee is taken.
     *
     * @param  array{basis_points: int, fixed_minor: int, cap_minor: int|null, waiver_threshold_minor: int|null}  $rule
     */
    private function paystackFee(array $rule, int $netMinor): int
    {
        if ($netMinor <= 0 || $rule['basis_points'] <= 0) {
            return 0;
        }
        $rate = $rule['basis_points'] / 10000;
        $withoutFixed = (int) ceil($netMinor / (1 - $rate));
        if ($rule['waiver_threshold_minor'] !== null && $withoutFixed < $rule['waiver_threshold_minor']) {
            $fee = $withoutFixed - $netMinor;
        } else {
            $fee = (int) ceil(($netMinor + $rule['fixed_minor']) / (1 - $rate)) - $netMinor;
        }
        if ($rule['cap_minor'] !== null) {
            $fee = min($fee, $rule['cap_minor']);
        }

        return max(0, $fee);
    }
}
