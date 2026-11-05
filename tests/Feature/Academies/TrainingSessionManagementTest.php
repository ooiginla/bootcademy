<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\Branch;
use App\Models\Programme;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TrainingSessionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_session_in_academy_timezone_and_sees_it_in_list_and_calendar(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $branch = Branch::factory()->for($academy)->create();
        $secondBranch = Branch::factory()->for($academy)->create();
        $programme = Programme::factory()->for($academy)->create();
        $coach = AcademyMembership::factory()->for(User::factory())->for($academy)->asRole(AcademyRole::Coach)->create();
        $secondCoach = AcademyMembership::factory()->for(User::factory())->for($academy)->asRole(AcademyRole::Coach)->create();
        $this->asAcademy($owner, $academy)->post(route('academy.sessions.store'), [
            'title' => 'Saturday training', 'all_branches' => true, 'branch_ids' => [], 'programme_id' => $programme->id,
            'coach_membership_ids' => [$coach->id, $secondCoach->id], 'starts_at' => '2026-11-07T08:00', 'ends_at' => '2026-11-07T10:00',
            'capacity' => 25, 'delivery_mode' => 'virtual', 'meeting_link' => 'https://meet.example.test/training', 'status' => 'scheduled',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $session = TrainingSession::query()->sole();
        $this->assertSame('2026-11-07 07:00:00', $session->starts_at->utc()->format('Y-m-d H:i:s'));
        $this->assertTrue($session->all_branches);
        $this->assertCount(2, $session->branches);
        $this->assertCount(2, $session->coaches);
        $this->assertSame('virtual', $session->delivery_mode);
        $this->asAcademy($owner, $academy)->get('/app/sessions?tab=calendar&month=2026-11')->assertInertia(fn (Assert $page) => $page
            ->component('academies/sessions')->where('active_tab', 'calendar')->has('calendar_sessions', 1)
            ->where('calendar_sessions.0.starts_at', '2026-11-07T08:00')->where('calendar_sessions.0.coach_names.0', $coach->user->name));
    }

    public function test_session_inputs_are_tenant_scoped_and_invalid_times_are_rejected(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        [, $other] = $this->member(AcademyRole::OwnerAdmin);
        $branch = Branch::factory()->for($academy)->create();
        $foreignProgramme = Programme::factory()->for($other)->create();
        $this->asAcademy($owner, $academy)->post(route('academy.sessions.store'), [
            'title' => 'Invalid', 'branch_id' => $branch->id, 'programme_id' => $foreignProgramme->id,
            'starts_at' => '2026-11-07T10:00', 'ends_at' => '2026-11-07T09:00', 'status' => 'scheduled',
        ])->assertSessionHasErrors(['programme_id', 'ends_at']);
        $this->assertDatabaseCount('training_sessions', 0);
    }

    public function test_coach_only_sees_assigned_sessions_and_cannot_manage_schedule(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $branch = Branch::factory()->for($academy)->create();
        $programme = Programme::factory()->for($academy)->create();
        $coachUser = User::factory()->create();
        $coach = AcademyMembership::factory()->for($coachUser)->for($academy)->asRole(AcademyRole::Coach)->create();
        foreach ([$coach->id, null] as $assignment) {
            $session = TrainingSession::query()->create(['academy_id' => $academy->id, 'branch_id' => $branch->id, 'programme_id' => $programme->id, 'coach_membership_id' => $assignment, 'title' => $assignment ? 'Mine' : 'Other', 'starts_at' => '2026-11-07 07:00:00', 'ends_at' => '2026-11-07 09:00:00', 'status' => 'scheduled', 'created_by_user_id' => $owner->id]);
            $session->branches()->attach($branch->id, ['academy_id' => $academy->id]);
            if ($assignment) {
                $session->coaches()->attach($assignment, ['academy_id' => $academy->id]);
            }
        }
        $this->asAcademy($coachUser, $academy)->get('/app/sessions?month=2026-11')->assertInertia(fn (Assert $page) => $page->has('sessions.data', 1)->where('sessions.data.0.title', 'Mine')->where('can_manage', false));
        $this->asAcademy($coachUser, $academy)->post(route('academy.sessions.store'), [])->assertForbidden();
    }

    /** @return array{User, Academy} */
    private function member(AcademyRole $role): array
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($academy)->asRole($role)->create();

        return [$user, $academy];
    }

    private function asAcademy(User $user, Academy $academy): static
    {
        return $this->actingAs($user)->withSession(['active_academy_id' => $academy->id]);
    }
}
