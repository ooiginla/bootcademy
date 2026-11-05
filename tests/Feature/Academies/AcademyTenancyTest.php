<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Enums\MembershipStatus;
use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AcademyTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_user_can_create_an_academy_and_becomes_its_owner_admin(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('academy.onboarding.store'), [
            'name' => 'All Stars Music Academy',
            'academy_type' => 'Music',
            'timezone' => 'Africa/Lagos',
            'currency' => 'ngn',
        ]);

        $academy = Academy::query()->sole();
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('active_academy_id', $academy->id);
        $this->assertDatabaseHas(AcademyMembership::class, [
            'academy_id' => $academy->id,
            'user_id' => $user->id,
            'role' => AcademyRole::OwnerAdmin->value,
            'status' => MembershipStatus::Active->value,
        ]);
        $this->assertSame('NGN', $academy->currency);
    }

    public function test_tampered_active_academy_session_falls_back_to_an_authorized_academy(): void
    {
        $user = User::factory()->create();
        $ownAcademy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($ownAcademy)->create();
        $foreignAcademy = Academy::factory()->create();

        $this->actingAs($user)
            ->withSession(['active_academy_id' => $foreignAcademy->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionHas('active_academy_id', $ownAcademy->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('academy.id', $ownAcademy->id)
                ->missing('academy.secret')
            );
    }

    public function test_user_cannot_activate_another_tenant(): void
    {
        $user = User::factory()->create();
        $ownAcademy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($ownAcademy)->create();
        $foreignAcademy = Academy::factory()->create();

        $this->actingAs($user)
            ->post(route('academy.activate', $foreignAcademy))
            ->assertNotFound();
    }

    public function test_user_can_switch_to_another_academy_with_an_active_membership(): void
    {
        $user = User::factory()->create();
        $first = Academy::factory()->for($user, 'creator')->create();
        $second = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($first)->create();
        AcademyMembership::factory()->for($user)->for($second)->create();

        $this->actingAs($user)
            ->post(route('academy.activate', $second))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('active_academy_id', $second->id)
            ->assertInertiaFlash('toast.message', 'Switched to '.$second->name.'.');
    }

    public function test_suspended_membership_cannot_be_used_to_activate_an_academy(): void
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($academy)->create([
            'status' => MembershipStatus::Suspended,
        ]);

        $this->actingAs($user)
            ->post(route('academy.activate', $academy))
            ->assertNotFound();
    }
}
