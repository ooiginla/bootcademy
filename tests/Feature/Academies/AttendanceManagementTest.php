<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Enums\EnrollmentStatus;
use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Player;
use App\Models\Programme;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AttendanceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_roster_contains_only_active_eligible_enrollments_in_the_session_scope(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        [$session, $branch, $programme] = $this->makeSession($academy, $owner);
        $eligible = $this->enroll($academy, $branch, $programme, 'Eligible', EnrollmentStatus::Active, '2026-10-01');
        $otherProgramme = Programme::factory()->for($academy)->create();
        $this->enroll($academy, $branch, $otherProgramme, 'Wrong programme', EnrollmentStatus::Active, '2026-10-01');
        $this->enroll($academy, $branch, $programme, 'Not started', EnrollmentStatus::Active, '2026-12-01');
        $this->enroll($academy, $branch, $programme, 'Inactive', EnrollmentStatus::Withdrawn, '2026-10-01');

        $this->asAcademy($owner, $academy)->get(route('academy.sessions.attendance.show', $session))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('academies/attendance')->has('roster', 1)
            ->where('roster.0.player_id', $eligible->id)
            ->where('summary.total', 1)->where('summary.marked', 0));
    }

    public function test_attendance_is_saved_once_and_corrections_are_immutably_logged(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        [$session, $branch, $programme] = $this->makeSession($academy, $owner);
        $player = $this->enroll($academy, $branch, $programme, 'Ada Player', EnrollmentStatus::Active, '2026-10-01');
        $route = route('academy.sessions.attendance.update', $session);

        $this->asAcademy($owner, $academy)->put($route, ['attendance' => [[
            'player_id' => $player->id, 'status' => 'present', 'notes' => 'On time',
        ]]])->assertRedirect()->assertInertiaFlash('toast.message', 'Attendance saved.');
        $this->asAcademy($owner, $academy)->put($route, ['attendance' => [[
            'player_id' => $player->id, 'status' => 'late', 'notes' => 'Arrived at 8:15',
        ]]])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('attendance_records', 1);
        $this->assertDatabaseHas('attendance_records', ['academy_id' => $academy->id, 'training_session_id' => $session->id, 'player_id' => $player->id, 'status' => 'late']);
        $this->assertDatabaseHas('attendance_change_logs', ['academy_id' => $academy->id, 'old_status' => 'present', 'new_status' => 'late', 'old_notes' => 'On time', 'new_notes' => 'Arrived at 8:15']);
    }

    public function test_only_assigned_coaches_or_managers_can_access_and_arbitrary_players_are_rejected(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        [$session, $branch, $programme] = $this->makeSession($academy, $owner);
        $assignedUser = User::factory()->create();
        $assigned = AcademyMembership::factory()->for($assignedUser)->for($academy)->asRole(AcademyRole::Coach)->create();
        $session->coaches()->attach($assigned->id, ['academy_id' => $academy->id]);
        $otherCoach = User::factory()->create();
        AcademyMembership::factory()->for($otherCoach)->for($academy)->asRole(AcademyRole::Coach)->create();
        $eligible = $this->enroll($academy, $branch, $programme, 'Eligible', EnrollmentStatus::Active, '2026-10-01');
        $notEligible = Player::factory()->for($academy)->create();
        $route = route('academy.sessions.attendance.update', $session);

        $this->asAcademy($assignedUser, $academy)->get(route('academy.sessions.attendance.show', $session))->assertOk();
        $this->asAcademy($assignedUser, $academy)->put($route, ['attendance' => [[
            'player_id' => $eligible->id, 'status' => 'present',
        ]]])->assertRedirect();
        $this->asAcademy($otherCoach, $academy)->get(route('academy.sessions.attendance.show', $session))->assertForbidden();
        $this->asAcademy($otherCoach, $academy)->put($route, ['attendance' => [[
            'player_id' => $eligible->id, 'status' => 'absent',
        ]]])->assertForbidden();
        $this->asAcademy($owner, $academy)->put($route, ['attendance' => [[
            'player_id' => $notEligible->id, 'status' => 'present',
        ]]])->assertSessionHasErrors('attendance');
        $this->assertDatabaseMissing('attendance_records', ['player_id' => $notEligible->id]);
        $this->assertDatabaseHas('attendance_records', ['player_id' => $eligible->id, 'status' => 'present']);

        [$foreignOwner, $foreignAcademy] = $this->member(AcademyRole::OwnerAdmin);
        $this->asAcademy($foreignOwner, $foreignAcademy)
            ->get(route('academy.sessions.attendance.show', $session))
            ->assertNotFound();
        $this->asAcademy($foreignOwner, $foreignAcademy)->put($route, ['attendance' => [[
            'player_id' => $eligible->id, 'status' => 'absent',
        ]]])->assertNotFound();
    }

    /** @return array{User, Academy} */
    private function member(AcademyRole $role): array
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($academy)->asRole($role)->create();

        return [$user, $academy];
    }

    /** @return array{TrainingSession, Branch, Programme} */
    private function makeSession(Academy $academy, User $owner): array
    {
        $branch = Branch::factory()->for($academy)->create();
        $programme = Programme::factory()->for($academy)->create();
        $session = TrainingSession::query()->create(['academy_id' => $academy->id, 'branch_id' => $branch->id, 'programme_id' => $programme->id, 'title' => 'Morning class', 'starts_at' => '2026-11-07 07:00:00', 'ends_at' => '2026-11-07 09:00:00', 'status' => 'scheduled', 'created_by_user_id' => $owner->id]);
        $session->branches()->attach($branch->id, ['academy_id' => $academy->id]);

        return [$session, $branch, $programme];
    }

    private function enroll(Academy $academy, Branch $branch, Programme $programme, string $name, EnrollmentStatus $status, string $startsOn): Player
    {
        $player = Player::factory()->for($academy)->create(['first_name' => $name, 'last_name' => 'Test']);
        Enrollment::factory()->for($player)->for($programme)->for($branch)->create(['academy_id' => $academy->id, 'status' => $status, 'starts_on' => $startsOn]);

        return $player;
    }

    private function asAcademy(User $user, Academy $academy): static
    {
        return $this->actingAs($user)->withSession(['active_academy_id' => $academy->id]);
    }
}
