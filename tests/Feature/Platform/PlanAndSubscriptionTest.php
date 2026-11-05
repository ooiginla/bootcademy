<?php

namespace Tests\Feature\Platform;

use App\Enums\SubscriptionStatus;
use App\Models\Academy;
use App\Models\AcademySubscription;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\SubscriptionEvent;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanAndSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_plan_catalog_is_seeded_with_minor_unit_prices(): void
    {
        $this->seed(PlanSeeder::class);

        $this->assertDatabaseHas(Plan::class, [
            'code' => 'essential',
            'monthly_price_minor' => 2_500_000,
            'quarterly_price_minor' => 7_500_000,
            'annual_price_minor' => 27_000_000,
            'active_player_limit' => 100,
        ]);
        $this->assertDatabaseHas(Plan::class, [
            'code' => 'professional',
            'annual_price_minor' => 48_600_000,
            'active_player_limit' => 300,
        ]);
        $this->assertDatabaseHas(Plan::class, [
            'code' => 'enterprise',
            'annual_price_minor' => 91_800_000,
            'active_player_limit' => 1_000,
        ]);
    }

    public function test_platform_super_admin_can_update_configurable_plan_values(): void
    {
        $admin = User::factory()->create(['is_platform_super_admin' => true]);
        $plan = Plan::factory()->create();

        $this->actingAs($admin)
            ->patch(route('platform.plans.update', $plan), [
                'name' => 'Growth',
                'monthly_ngn' => 30_000,
                'quarterly_ngn' => 90_000,
                'annual_ngn' => 324_000,
                'active_player_limit' => 150,
                'is_active' => true,
            ])
            ->assertRedirect(route('platform.plans.index'))
            ->assertInertiaFlash('toast.message', 'Plan updated.');

        $this->assertDatabaseHas(Plan::class, [
            'id' => $plan->id,
            'name' => 'Growth',
            'monthly_price_minor' => 3_000_000,
            'quarterly_price_minor' => 9_000_000,
            'annual_price_minor' => 32_400_000,
            'active_player_limit' => 150,
        ]);
        $this->assertDatabaseHas(AuditLog::class, [
            'actor_user_id' => $admin->id,
            'action' => 'platform.plan.updated',
        ]);
    }

    public function test_regular_user_cannot_change_plan_or_subscription(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        $academy = Academy::factory()->create();

        $this->actingAs($user)
            ->patch(route('platform.plans.update', $plan), $this->planPayload())
            ->assertForbidden();

        $this->actingAs($user)
            ->patch(route('platform.academies.subscription.update', $academy), [
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount(AcademySubscription::class, 0);
    }

    public function test_subscription_assignment_and_changes_preserve_event_history(): void
    {
        $admin = User::factory()->create(['is_platform_super_admin' => true]);
        $academy = Academy::factory()->create();
        $essential = Plan::factory()->create(['code' => 'essential']);
        $professional = Plan::factory()->create(['code' => 'professional']);

        $this->actingAs($admin)
            ->patch(route('platform.academies.subscription.update', $academy), [
                'plan_id' => $essential->id,
                'status' => SubscriptionStatus::Active->value,
            ])
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', 'Academy subscription updated.');

        $subscription = AcademySubscription::query()->sole();

        $this->actingAs($admin)
            ->patch(route('platform.academies.subscription.update', $academy), [
                'plan_id' => $professional->id,
                'status' => SubscriptionStatus::Suspended->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount(AcademySubscription::class, 1);
        $this->assertSame($subscription->id, AcademySubscription::query()->sole()->id);
        $this->assertDatabaseHas(AcademySubscription::class, [
            'academy_id' => $academy->id,
            'plan_id' => $professional->id,
            'status' => SubscriptionStatus::Suspended->value,
        ]);
        $this->assertDatabaseCount(SubscriptionEvent::class, 2);
        $this->assertDatabaseHas(SubscriptionEvent::class, [
            'academy_id' => $academy->id,
            'event' => 'subscription.assigned',
        ]);
        $this->assertDatabaseHas(SubscriptionEvent::class, [
            'academy_id' => $academy->id,
            'event' => 'subscription.changed',
        ]);
    }

    public function test_inactive_plan_cannot_be_newly_assigned(): void
    {
        $admin = User::factory()->create(['is_platform_super_admin' => true]);
        $academy = Academy::factory()->create();
        $plan = Plan::factory()->create(['is_active' => false]);

        $this->actingAs($admin)
            ->patch(route('platform.academies.subscription.update', $academy), [
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active->value,
            ])
            ->assertSessionHasErrors('plan_id');

        $this->assertDatabaseCount(AcademySubscription::class, 0);
    }

    /** @return array<string, mixed> */
    private function planPayload(): array
    {
        return [
            'name' => 'Blocked',
            'monthly_ngn' => 1,
            'quarterly_ngn' => 3,
            'annual_ngn' => 10,
            'active_player_limit' => 1,
            'is_active' => true,
        ];
    }
}
