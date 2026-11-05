<?php

namespace Tests\Feature\Auth;

use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RequiredPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_created_user_must_replace_temporary_password_before_dashboard_access(): void
    {
        config()->set('auth.require_email_verification', false);
        $user = User::factory()->create(['must_change_password' => true]);
        $academy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($academy)->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('password.required.edit'));

        $this->actingAs($user)
            ->get(route('password.required.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/change-temporary-password'));

        $this->actingAs($user)
            ->put(route('password.required.update'), [
                'password' => 'My-Personal-Password-456!',
                'password_confirmation' => 'My-Personal-Password-456!',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertInertiaFlash('toast.message', 'Your personal password is now set.');

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('My-Personal-Password-456!', $user->password));
    }

    public function test_regular_user_cannot_use_required_password_screen_unnecessarily(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($user)
            ->get(route('password.required.edit'))
            ->assertRedirect(route('dashboard'));
    }
}
