<?php

namespace Tests\Feature;

use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($academy)->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('auth.user.id', $user->id)
                ->where('auth.user.email', $user->email)
                ->where('academy.id', $academy->id)
                ->where('academy.can_manage', true)
            );
    }

    public function test_unverified_users_are_redirected_when_verification_is_required(): void
    {
        config()->set('auth.require_email_verification', true);

        $user = User::factory()->unverified()->create();
        $academy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($academy)->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_users_can_visit_the_dashboard_when_local_bypass_is_enabled(): void
    {
        config()->set('auth.require_email_verification', false);

        $user = User::factory()->unverified()->create();
        $academy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($academy)->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_users_without_an_academy_are_sent_to_onboarding(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('academy.onboarding.create'));
    }
}
