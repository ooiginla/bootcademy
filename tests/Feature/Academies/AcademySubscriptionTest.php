<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\AcademySubscription;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AcademySubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_admin_can_view_only_active_academy_subscription_summary(): void
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($academy)->create();
        $plan = Plan::factory()->create(['name' => 'Professional', 'active_player_limit' => 300]);
        AcademySubscription::factory()->for($academy)->for($plan)->create();
        AcademySubscription::factory()->create();

        $this->actingAs($user)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.subscription.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('academies/subscription')
                ->where('subscription.plan.name', 'Professional')
                ->where('subscription.plan.active_player_limit', 300)
            );
    }

    public function test_non_admin_member_cannot_view_subscription(): void
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->create();
        AcademyMembership::factory()->for($user)->for($academy)->asRole(AcademyRole::Coach)->create();

        $this->actingAs($user)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.subscription.show'))
            ->assertForbidden();
    }
}
