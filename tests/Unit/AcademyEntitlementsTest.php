<?php

namespace Tests\Unit;

use App\Enums\SubscriptionStatus;
use App\Models\Academy;
use App\Models\AcademySubscription;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Platform\AcademyEntitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademyEntitlementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_subscription_exposes_data_driven_limits_and_features(): void
    {
        $academy = Academy::factory()->create();
        $plan = Plan::factory()->create(['active_player_limit' => 300]);
        PlanFeature::create([
            'plan_id' => $plan->id,
            'feature_key' => 'advanced_reports',
            'enabled' => true,
            'limit' => 12,
        ]);
        AcademySubscription::factory()->for($academy)->for($plan)->create();

        $entitlements = AcademyEntitlements::for($academy);

        $this->assertTrue($entitlements->hasActiveSubscription());
        $this->assertSame(300, $entitlements->activePlayerLimit());
        $this->assertTrue($entitlements->allows('advanced_reports'));
        $this->assertSame(12, $entitlements->featureLimit('advanced_reports'));
        $this->assertFalse($entitlements->allows('unknown_feature'));
    }

    public function test_missing_or_suspended_subscription_grants_no_entitlements(): void
    {
        $withoutSubscription = Academy::factory()->create();
        $this->assertFalse(AcademyEntitlements::for($withoutSubscription)->hasActiveSubscription());
        $this->assertSame(0, AcademyEntitlements::for($withoutSubscription)->activePlayerLimit());

        $suspendedAcademy = Academy::factory()->create();
        AcademySubscription::factory()->for($suspendedAcademy)->create([
            'status' => SubscriptionStatus::Suspended,
        ]);

        $entitlements = AcademyEntitlements::for($suspendedAcademy);
        $this->assertFalse($entitlements->hasActiveSubscription());
        $this->assertSame(0, $entitlements->activePlayerLimit());
        $this->assertFalse($entitlements->allows('advanced_reports'));
    }
}
