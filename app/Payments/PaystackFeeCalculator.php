<?php

namespace App\Payments;

use App\Models\Academy;

class PaystackFeeCalculator
{
    public function surcharge(Academy $academy, int $baseMinor): int
    {
        if ($baseMinor <= 0 || $academy->paystack_fee_basis_points <= 0) {
            return 0;
        }

        $rate = $academy->paystack_fee_basis_points / 10000;
        $fixed = (int) $academy->paystack_fee_fixed_minor;
        $threshold = $academy->paystack_fee_waiver_threshold_minor;
        $withoutFixed = (int) ceil($baseMinor / (1 - $rate));
        if ($threshold !== null && $withoutFixed < $threshold) {
            $fee = $withoutFixed - $baseMinor;
        } else {
            $fee = (int) ceil(($baseMinor + $fixed) / (1 - $rate)) - $baseMinor;
        }

        if ($academy->paystack_fee_cap_minor !== null) {
            $fee = min($fee, (int) $academy->paystack_fee_cap_minor);
        }

        return max(0, $fee);
    }
}
