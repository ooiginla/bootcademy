<?php

namespace Tests\Feature\Academies;

use App\Academies\ApplicationIntakeAvailability;
use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyCycle;
use App\Models\AcademyMembership;
use App\Models\AcademyWebsite;
use App\Models\Application;
use App\Models\ApplicationIntake;
use App\Models\Branch;
use App\Models\Cohort;
use App\Models\Programme;
use App\Models\ProgrammeCycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApplicationIntakeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_cohort_and_open_it_for_applications_in_one_action(): void
    {
        [$owner, $academy] = $this->member();
        [$offering, $branch] = $this->offering($academy);

        $this->asAcademy($owner, $academy)->post(route('academy.cohorts.store'), [
            'programme_cycle_id' => $offering->id,
            'branch_id' => $branch->id,
            'name' => 'January 2027 cohort',
            'code' => 'JAN-27',
            'starts_on' => '2027-01-08',
            'ends_on' => '2027-06-20',
            'capacity' => 30,
            'delivery_mode' => 'hybrid',
            'status' => 'open',
            'is_receiving_applications' => true,
            'applications_open_on' => '2026-01-01',
            'applications_close_on' => '2027-01-07',
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Cohort created.');

        $cohort = Cohort::query()->whereBelongsTo($academy)->sole();
        $intake = ApplicationIntake::query()->whereBelongsTo($cohort)->sole();
        $this->assertTrue($intake->is_enabled);
        $this->assertSame('2026-01-01', $intake->opens_on?->toDateString());
        $this->assertSame('2027-01-07', $intake->closes_on?->toDateString());

        $this->asAcademy($owner, $academy)->get(route('academy.applications.index', ['tab' => 'cohorts']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('academies/applications')
                ->where('active_tab', 'cohorts')
                ->has('cohorts', 1)
                ->where('cohorts.0.name', 'January 2027 cohort')
                ->where('cohorts.0.is_receiving_applications', true)
                ->where('cohorts.0.is_open_for_applications', true)
                ->where('cohorts.0.applications_open_on', '2026-01-01')
                ->where('cohorts.0.applications_close_on', '2027-01-07'));

        $this->asAcademy($owner, $academy)->patch(route('academy.cohorts.update', $cohort), [
            'programme_cycle_id' => $offering->id,
            'branch_id' => $branch->id,
            'name' => 'January 2027 cohort',
            'code' => 'JAN-27',
            'starts_on' => '2027-01-08',
            'ends_on' => '2027-06-20',
            'capacity' => 30,
            'delivery_mode' => 'hybrid',
            'status' => 'open',
            'is_receiving_applications' => false,
            'applications_open_on' => '2026-01-01',
            'applications_close_on' => '2027-01-07',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse($intake->fresh()->is_enabled);
        $this->assertSame(1, ApplicationIntake::query()->whereBelongsTo($cohort)->count());
    }

    public function test_closed_or_disabled_settings_are_not_exposed_to_applicants(): void
    {
        [$owner, $academy] = $this->member();
        [$offering, $branch] = $this->offering($academy);
        $website = AcademyWebsite::query()->create([
            'academy_id' => $academy->id,
            'subdomain' => 'closed-intake',
            'theme_key' => 'launch',
            'is_published' => true,
            'sections' => [],
        ]);
        $cohort = Cohort::query()->create([
            'academy_id' => $academy->id,
            'programme_cycle_id' => $offering->id,
            'branch_id' => $branch->id,
            'name' => 'Closed cohort',
            'starts_on' => '2027-01-01',
            'status' => 'open',
        ]);
        ApplicationIntake::query()->create([
            'academy_id' => $academy->id,
            'programme_cycle_id' => $offering->id,
            'cohort_id' => $cohort->id,
            'cohort_scope_id' => $cohort->id,
            'branch_id' => $branch->id,
            'branch_scope_id' => $branch->id,
            'is_enabled' => false,
        ]);

        $this->get(route('public.academy.application', $website->subdomain))
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/application')
                ->has('intakes', 0));

        $this->asAcademy($owner, $academy)->get(route('academy.applications.index', ['tab' => 'cohorts']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('active_tab', 'cohorts')
                ->where('cohorts.0.is_receiving_applications', false)
                ->where('cohorts.0.is_open_for_applications', false));
    }

    public function test_cross_tenant_cohort_and_application_setting_updates_are_not_found(): void
    {
        [$owner, $academy] = $this->member();
        [, $foreignAcademy] = $this->member();
        [$foreignOffering, $foreignBranch] = $this->offering($foreignAcademy);
        $foreignCohort = Cohort::query()->create([
            'academy_id' => $foreignAcademy->id,
            'programme_cycle_id' => $foreignOffering->id,
            'branch_id' => $foreignBranch->id,
            'name' => 'Foreign cohort',
            'starts_on' => '2027-01-01',
            'status' => 'open',
        ]);
        $foreignIntake = ApplicationIntake::query()->create([
            'academy_id' => $foreignAcademy->id,
            'programme_cycle_id' => $foreignOffering->id,
            'cohort_id' => $foreignCohort->id,
            'cohort_scope_id' => $foreignCohort->id,
            'branch_id' => $foreignBranch->id,
            'branch_scope_id' => $foreignBranch->id,
            'is_enabled' => true,
        ]);

        $this->asAcademy($owner, $academy)->patch(route('academy.cohorts.update', $foreignCohort), [
            'programme_cycle_id' => $foreignOffering->id,
            'name' => 'Changed',
            'starts_on' => '2027-01-01',
            'delivery_mode' => 'physical',
            'status' => 'open',
        ])->assertNotFound();

        $this->asAcademy($owner, $academy)->patch(route('academy.application-intakes.update', $foreignIntake), [
            'programme_cycle_id' => $foreignOffering->id,
            'cohort_id' => $foreignCohort->id,
            'branch_id' => $foreignBranch->id,
            'is_enabled' => false,
        ])->assertNotFound();
    }

    public function test_intake_scope_with_applications_is_immutable_for_audit_history(): void
    {
        [$owner, $academy] = $this->member();
        [$offering, $branch] = $this->offering($academy);
        [$otherOffering, $otherBranch] = $this->offering($academy);
        $intake = ApplicationIntake::query()->create([
            'academy_id' => $academy->id,
            'programme_cycle_id' => $offering->id,
            'cohort_scope_id' => 0,
            'branch_id' => $branch->id,
            'branch_scope_id' => $branch->id,
            'is_enabled' => true,
        ]);
        Application::query()->create([
            'academy_id' => $academy->id,
            'application_intake_id' => $intake->id,
            'programme_id' => $offering->programme_id,
            'programme_cycle_id' => $offering->id,
            'branch_id' => $branch->id,
            'reference' => 'APP-INTAKE-HISTORY',
            'first_name' => 'Ada',
            'last_name' => 'Okoro',
            'status' => 'submitted',
        ]);

        $this->asAcademy($owner, $academy)->patch(route('academy.application-intakes.update', $intake), [
            'programme_cycle_id' => $otherOffering->id,
            'branch_id' => $otherBranch->id,
            'is_enabled' => true,
        ])->assertSessionHasErrors('programme_cycle_id');

        $this->assertSame($offering->id, $intake->fresh()->programme_cycle_id);
        $this->assertSame($branch->id, $intake->fresh()->branch_id);
    }

    public function test_accepting_cohort_application_creates_membership_without_changing_finance_scope(): void
    {
        [$owner, $academy] = $this->member();
        [$offering, $branch, $programme] = $this->offering($academy);
        $cohort = Cohort::query()->create([
            'academy_id' => $academy->id,
            'programme_cycle_id' => $offering->id,
            'branch_id' => $branch->id,
            'name' => 'January cohort',
            'starts_on' => '2026-01-01',
            'capacity' => 1,
            'status' => 'open',
        ]);
        $intake = ApplicationIntake::query()->create([
            'academy_id' => $academy->id,
            'programme_cycle_id' => $offering->id,
            'cohort_id' => $cohort->id,
            'cohort_scope_id' => $cohort->id,
            'branch_id' => $branch->id,
            'branch_scope_id' => $branch->id,
            'is_enabled' => true,
        ]);
        $application = Application::query()->create([
            'academy_id' => $academy->id,
            'reference' => 'APP-COHORT-1',
            'first_name' => 'Amara',
            'last_name' => 'Okafor',
            'programme_id' => $programme->id,
            'programme_cycle_id' => $offering->id,
            'cohort_id' => $cohort->id,
            'branch_id' => $branch->id,
            'status' => 'submitted',
        ]);

        $this->asAcademy($owner, $academy)->patch(route('academy.applications.review', $application), [
            'decision' => 'accept',
            'programme_id' => $programme->id,
            'branch_id' => $branch->id,
            'activation_on' => '2026-11-06',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertNotNull($application->converted_player_id);
        $this->assertDatabaseHas('enrollments', [
            'academy_id' => $academy->id,
            'programme_cycle_id' => $offering->id,
            'player_id' => $application->converted_player_id,
        ]);
        $this->assertDatabaseHas('cohort_memberships', [
            'academy_id' => $academy->id,
            'cohort_id' => $cohort->id,
            'player_id' => $application->converted_player_id,
            'status' => 'active',
        ]);
        $this->assertFalse(app(ApplicationIntakeAvailability::class)->query($academy)->whereKey($intake)->exists());
    }

    /** @return array{ProgrammeCycle, Branch, Programme} */
    private function offering(Academy $academy): array
    {
        $programme = Programme::factory()->for($academy)->create();
        $branch = Branch::factory()->for($academy)->create();
        $cycle = AcademyCycle::query()->create([
            'academy_id' => $academy->id,
            'name' => '2026/2027 Academic Year '.$programme->id,
            'type' => 'fixed',
            'starts_on' => '2026-01-01',
            'ends_on' => '2027-12-31',
            'status' => 'active',
        ]);
        $offering = ProgrammeCycle::query()->create([
            'academy_id' => $academy->id,
            'academy_cycle_id' => $cycle->id,
            'programme_id' => $programme->id,
            'branch_id' => $branch->id,
            'branch_scope_id' => $branch->id,
            'fee_name' => 'Tuition',
            'currency' => 'NGN',
            'application_fee_minor' => 0,
            'payment_mode' => 'one_off',
            'enrollment_payment_rule' => 'full_payment',
            'is_active' => true,
        ]);

        return [$offering, $branch, $programme];
    }

    /** @return array{User, Academy} */
    private function member(): array
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($academy)->asRole(AcademyRole::OwnerAdmin)->create();

        return [$user, $academy];
    }

    private function asAcademy(User $user, Academy $academy): static
    {
        return $this->actingAs($user)->withSession(['active_academy_id' => $academy->id]);
    }
}
