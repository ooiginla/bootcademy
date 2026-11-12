<?php

namespace Tests\Feature\Payments;

use App\Models\Academy;
use App\Models\PlatformSetting;
use App\Payments\PaystackFeeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaystackFeeCalculatorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_grosses_up_the_percentage_and_waives_the_fixed_fee_below_the_threshold(): void
    {
        // Default platform settings: 1.5% + ₦100, ₦100 waived below ₦2,500, capped at ₦2,000, no platform fee.
        $this->assertSame(1523, app(PaystackFeeCalculator::class)->surcharge(Academy::factory()->create(), 100000));
    }

    #[Test]
    public function it_adds_the_fixed_fee_above_the_threshold_and_honours_the_cap(): void
    {
        $academy = Academy::factory()->create();
        $calculator = app(PaystackFeeCalculator::class);

        $this->assertSame(25381, $calculator->surcharge($academy, 1000000));
        $this->assertSame(200000, $calculator->surcharge($academy, 50000000));
    }

    #[Test]
    public function platform_paystack_settings_change_the_customer_fee(): void
    {
        PlatformSetting::current()->update([
            'paystack_fee_basis_points' => 200, 'paystack_fee_fixed_minor' => 15000,
            'paystack_fee_cap_minor' => null, 'paystack_fee_waiver_threshold_minor' => null,
        ]);

        $this->assertSame(47960, app(PaystackFeeCalculator::class)->surcharge(Academy::factory()->create(), 1600000));
    }
}
