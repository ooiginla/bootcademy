<?php

namespace Tests\Unit;

use App\Models\Academy;
use App\Payments\PaystackFeeCalculator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PaystackFeeCalculatorTest extends TestCase
{
    #[Test]
    public function it_grosses_up_the_percentage_and_waives_the_fixed_fee_below_the_threshold(): void
    {
        $academy = new Academy([
            'paystack_fee_basis_points' => 150,
            'paystack_fee_fixed_minor' => 10000,
            'paystack_fee_cap_minor' => 200000,
            'paystack_fee_waiver_threshold_minor' => 250000,
        ]);

        $this->assertSame(1523, (new PaystackFeeCalculator)->surcharge($academy, 100000));
    }

    #[Test]
    public function it_adds_the_fixed_fee_above_the_threshold_and_honours_the_cap(): void
    {
        $academy = new Academy([
            'paystack_fee_basis_points' => 150,
            'paystack_fee_fixed_minor' => 10000,
            'paystack_fee_cap_minor' => 200000,
            'paystack_fee_waiver_threshold_minor' => 250000,
        ]);
        $calculator = new PaystackFeeCalculator;

        $this->assertSame(25381, $calculator->surcharge($academy, 1000000));
        $this->assertSame(200000, $calculator->surcharge($academy, 50000000));
    }

    #[Test]
    public function academy_specific_configuration_changes_the_customer_fee(): void
    {
        $academy = new Academy([
            'paystack_fee_basis_points' => 200,
            'paystack_fee_fixed_minor' => 15000,
            'paystack_fee_cap_minor' => null,
            'paystack_fee_waiver_threshold_minor' => null,
        ]);

        $this->assertSame(47960, (new PaystackFeeCalculator)->surcharge($academy, 1600000));
    }
}
