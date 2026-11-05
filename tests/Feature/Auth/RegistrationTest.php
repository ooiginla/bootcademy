<?php

namespace Tests\Feature\Auth;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'academy_name' => 'Bright Futures Academy',
            'academy_type' => 'Language',
            'timezone' => 'Africa/Lagos',
            'currency' => 'NGN',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas(Academy::class, [
            'name' => 'Bright Futures Academy',
            'academy_type' => 'Language',
        ]);
        $this->assertDatabaseHas(AcademyMembership::class, [
            'role' => AcademyRole::OwnerAdmin->value,
        ]);
    }
}
