<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyCustomField;
use App\Models\AcademyMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CoachManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_only_the_academys_coaches_and_can_search_them(): void
    {
        [$owner, $academy] = $this->owner();
        $coach = User::factory()->create(['name' => 'Ada Instructor', 'email' => 'ada@example.test']);
        AcademyMembership::factory()->for($coach)->for($academy)->asRole(AcademyRole::Coach)->create();
        AcademyMembership::factory()->for(User::factory())->for($academy)->asRole(AcademyRole::FinanceOfficer)->create();
        [, $foreignAcademy] = $this->owner();
        AcademyMembership::factory()->for(User::factory())->for($foreignAcademy)->asRole(AcademyRole::Coach)->create();

        $this->asAcademy($owner, $academy)->get(route('academy.coaches.index', ['search' => 'Ada']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('academies/coaches')
            ->has('coaches.data', 1)
            ->where('coaches.data.0.name', 'Ada Instructor')
            ->where('coaches.data.0.email', 'ada@example.test')
            ->where('coaches.data.0.profile', null)
            ->where('filters.search', 'Ada'));
    }

    public function test_owner_can_save_tenant_scoped_coach_biodata_and_custom_values(): void
    {
        [$owner, $academy] = $this->owner();
        $membership = AcademyMembership::factory()->for(User::factory())->for($academy)->asRole(AcademyRole::Coach)->create();
        $field = AcademyCustomField::query()->create([
            'academy_id' => $academy->id, 'entity_type' => 'coach', 'key' => 'certification',
            'label' => 'Certification', 'type' => 'text', 'is_required' => true,
            'is_sensitive' => false, 'is_active' => true, 'sort_order' => 0,
        ]);

        $this->asAcademy($owner, $academy)->patch(route('academy.coaches.update', $membership), [
            'first_name' => 'Tunde', 'middle_name' => 'Ayo', 'last_name' => 'Bello',
            'date_of_birth' => '1988-04-12', 'gender' => 'male', 'phone' => '08010000000',
            'whatsapp_number' => '08010000001', 'home_address' => '12 Lagos Street',
            'city' => 'Ikeja', 'state_region' => 'Lagos', 'country' => 'Nigeria',
            'years_of_experience' => 12, 'biography' => 'Youth development specialist.',
            'custom_fields' => ['certification' => 'UEFA B'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('academy_coach_profiles', [
            'academy_id' => $academy->id, 'membership_id' => $membership->id,
            'first_name' => 'Tunde', 'years_of_experience' => 12,
        ]);
        $profileId = (int) $membership->coachProfile()->value('id');
        $this->assertDatabaseHas('coach_custom_field_values', [
            'academy_id' => $academy->id, 'coach_profile_id' => $profileId,
            'custom_field_id' => $field->id, 'value' => json_encode('UEFA B'),
        ]);
        $this->assertDatabaseHas('audit_logs', ['academy_id' => $academy->id, 'action' => 'academy.coach_profile.created']);
    }

    public function test_coach_profiles_and_custom_fields_cannot_be_managed_without_owner_access_or_across_tenants(): void
    {
        [$owner, $academy] = $this->owner();
        $coachUser = User::factory()->create();
        $coachMembership = AcademyMembership::factory()->for($coachUser)->for($academy)->asRole(AcademyRole::Coach)->create();
        [, $foreignAcademy] = $this->owner();
        $foreignMembership = AcademyMembership::factory()->for(User::factory())->for($foreignAcademy)->asRole(AcademyRole::Coach)->create();

        $this->asAcademy($coachUser, $academy)->get(route('academy.coaches.index'))->assertForbidden();
        $this->asAcademy($coachUser, $academy)->patch(route('academy.coaches.update', $coachMembership), [])->assertForbidden();
        $this->asAcademy($owner, $academy)->patch(route('academy.coaches.update', $foreignMembership), [])->assertNotFound();

        $this->asAcademy($owner, $academy)->post(route('academy.coach-fields.store'), [
            'key' => 'speciality', 'label' => 'Speciality', 'type' => 'text', 'options' => [],
            'is_required' => false, 'is_sensitive' => false, 'is_active' => true, 'sort_order' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('academy_custom_fields', [
            'academy_id' => $academy->id, 'entity_type' => 'coach', 'key' => 'speciality',
        ]);
        $this->assertDatabaseMissing('academy_custom_fields', [
            'academy_id' => $foreignAcademy->id, 'key' => 'speciality',
        ]);
    }

    /** @return array{User, Academy} */
    private function owner(): array
    {
        $owner = User::factory()->create();
        $academy = Academy::factory()->for($owner, 'creator')->create();
        AcademyMembership::factory()->for($owner)->for($academy)->asRole(AcademyRole::OwnerAdmin)->create();

        return [$owner, $academy];
    }

    private function asAcademy(User $user, Academy $academy): static
    {
        return $this->actingAs($user)->withSession(['active_academy_id' => $academy->id]);
    }
}
