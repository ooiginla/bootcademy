<?php

namespace Tests\Unit;

use App\Enums\SubscriptionStatus;
use App\Models\Academy;
use App\Models\AcademySubscriptionPeriod;
use App\Models\Plan;
use App\Platform\AcademyEntitlements;
use App\Platform\Subscriptions\AddSubscriptionPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AcademyEntitlementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_trial_gives_the_trial_plan_and_its_features(): void
    {
        $academy = Academy::factory()->create();
        $entitlements = AcademyEntitlements::for($academy);

        $this->assertTrue($entitlements->hasAccess());
        $this->assertSame('trialing', $entitlements->state());
        $this->assertSame('enterprise', $entitlements->plan()?->code);
        $this->assertSame(750, $entitlements->activePlayerLimit());
        $this->assertTrue($entitlements->allows('api_access'));
        $this->assertFalse($entitlements->allows('unknown_feature'));
    }

    public function test_after_the_trial_the_academy_falls_back_to_essential_without_access(): void
    {
        $academy = Academy::factory()->create();
        AcademySubscriptionPeriod::query()->where('academy_id', $academy->id)->update(['ends_at' => now()->subMinute()]);
        $entitlements = AcademyEntitlements::for($academy);

        $this->assertFalse($entitlements->hasAccess());
        $this->assertSame('lapsed', $entitlements->state());
        $this->assertSame('essential', $entitlements->plan()?->code);
        $this->assertSame(0, $entitlements->activePlayerLimit());
        $this->assertFalse($entitlements->allows('api_access'));
        $this->assertNull($entitlements->accessEndsAt());
    }

    public function test_missing_or_suspended_subscriptions_grant_no_access(): void
    {
        $this->assertFalse(AcademyEntitlements::for(Academy::factory()->withoutSubscription()->create())->hasAccess());

        $suspended = Academy::factory()->create();
        $suspended->subscription()->update(['status' => SubscriptionStatus::Suspended]);
        $entitlements = AcademyEntitlements::for($suspended);
        $this->assertFalse($entitlements->hasAccess());
        $this->assertSame('suspended', $entitlements->state());
    }

    public function test_access_runs_through_back_to_back_periods(): void
    {
        $academy = Academy::factory()->create();
        $essential = Plan::query()->where('code', 'essential')->firstOrFail();
        $add = app(AddSubscriptionPeriod::class);
        $first = $add->handle($academy, $essential, 'granted', null, null, null, Request::create('/'), now()->addMonth());
        $second = $add->handle($academy, $essential, 'granted', null, null, null, Request::create('/'), now()->addMonths(3));
        $entitlements = AcademyEntitlements::for($academy);

        $this->assertSame('active', $entitlements->state());
        $this->assertSame($first->id, $entitlements->currentPeriod()?->id);
        $this->assertTrue($entitlements->accessEndsAt()?->equalTo($second->ends_at));
        $this->assertCount(1, $entitlements->upcomingPeriods());
        // The first paid period ended the trial straight away.
        $this->assertSame(0, AcademySubscriptionPeriod::query()->where('kind', 'trial')->where('ends_at', '>', now())->count());
    }
}
