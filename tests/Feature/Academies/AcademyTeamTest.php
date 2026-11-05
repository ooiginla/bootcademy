<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Enums\MembershipStatus;
use App\Models\Academy;
use App\Models\AcademyInvitation;
use App\Models\AcademyMembership;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use App\Notifications\AcademyInvitationNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AcademyTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_admin_can_create_an_active_member_with_a_temporary_password(): void
    {
        Event::fake([Registered::class]);
        [$owner, $academy] = $this->owner();
        $branch = Branch::factory()->for($academy)->create();

        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.team.create'), [
                'name' => 'Created Coach',
                'email' => 'created.coach@example.test',
                'role' => AcademyRole::Coach->value,
                'password' => 'Temporary-Password-123!',
                'password_confirmation' => 'Temporary-Password-123!',
                'branch_ids' => [$branch->id],
            ])
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', 'Team account created and activated.');

        $user = User::query()->where('email', 'created.coach@example.test')->sole();
        $this->assertTrue($user->must_change_password);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('Temporary-Password-123!', $user->password));
        $this->assertDatabaseHas(AcademyMembership::class, [
            'academy_id' => $academy->id,
            'user_id' => $user->id,
            'role' => AcademyRole::Coach->value,
            'status' => MembershipStatus::Active->value,
        ]);
        $membership = AcademyMembership::query()->where('user_id', $user->id)->sole();
        $this->assertSame([$branch->id], $membership->branches()->pluck('branches.id')->all());
        $this->assertDatabaseHas(AuditLog::class, [
            'academy_id' => $academy->id,
            'actor_user_id' => $owner->id,
            'action' => 'academy.member.created',
        ]);
        Event::assertDispatched(Registered::class, fn (Registered $event): bool => $event->user->is($user));
    }

    public function test_admin_cannot_create_a_second_account_for_an_existing_email(): void
    {
        [$owner, $academy] = $this->owner();
        $existing = User::factory()->create();

        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.team.create'), [
                'name' => 'Duplicate',
                'email' => $existing->email,
                'role' => AcademyRole::Coach->value,
                'password' => 'Temporary-Password-123!',
                'password_confirmation' => 'Temporary-Password-123!',
            ])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount(User::class, 2);
    }

    public function test_owner_admin_can_invite_an_existing_user_to_an_academy_role(): void
    {
        Notification::fake();
        [$owner, $academy] = $this->owner();
        $branch = Branch::factory()->for($academy)->create();
        $email = 'new.coach@example.test';

        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.team.store'), [
                'email' => strtoupper($email),
                'role' => AcademyRole::Coach->value,
                'branch_ids' => [$branch->id],
            ])
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', 'Invitation email sent.');

        $this->assertDatabaseHas(AcademyInvitation::class, [
            'academy_id' => $academy->id,
            'email' => $email,
            'role' => AcademyRole::Coach->value,
            'invited_by_user_id' => $owner->id,
        ]);
        $invitation = AcademyInvitation::query()->where('email', $email)->sole();
        $this->assertSame([$branch->id], $invitation->branches()->pluck('branches.id')->all());
        $this->assertDatabaseHas(AuditLog::class, [
            'academy_id' => $academy->id,
            'actor_user_id' => $owner->id,
            'action' => 'academy.invitation.created',
        ]);
        Notification::assertSentOnDemand(AcademyInvitationNotification::class);
    }

    public function test_invited_user_can_accept_and_switch_into_the_academy(): void
    {
        [$owner, $academy] = $this->owner();
        $invitee = User::factory()->create();
        $invitation = AcademyMembership::factory()->for($invitee)->for($academy)->create([
            'role' => AcademyRole::FinanceOfficer,
            'status' => MembershipStatus::Invited,
            'joined_at' => null,
            'invited_by_user_id' => $owner->id,
        ]);

        $this->actingAs($invitee)
            ->post(route('academy.invitations.accept', $invitation))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('active_academy_id', $academy->id);

        $this->assertSame(MembershipStatus::Active, $invitation->fresh()->status);
        $this->assertNotNull($invitation->fresh()->joined_at);
        $this->assertDatabaseHas(AuditLog::class, [
            'academy_id' => $academy->id,
            'actor_user_id' => $invitee->id,
            'action' => 'academy.invitation.accepted',
        ]);
    }

    public function test_user_with_only_a_pending_invitation_is_sent_to_invitations_after_login(): void
    {
        [$owner, $academy] = $this->owner();
        $invitee = User::factory()->create();
        AcademyMembership::factory()->for($invitee)->for($academy)->create([
            'status' => MembershipStatus::Invited,
            'invited_by_user_id' => $owner->id,
        ]);

        $this->actingAs($invitee)
            ->get(route('dashboard'))
            ->assertRedirect(route('academy.onboarding.create'));

        $this->actingAs($invitee)
            ->get(route('academy.onboarding.create'))
            ->assertRedirect(route('academy.invitations.index'));
    }

    public function test_another_user_cannot_accept_someone_elses_invitation(): void
    {
        [$owner, $academy] = $this->owner();
        $invitee = User::factory()->create();
        $invitation = AcademyMembership::factory()->for($invitee)->for($academy)->create([
            'status' => MembershipStatus::Invited,
            'invited_by_user_id' => $owner->id,
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('academy.invitations.accept', $invitation))
            ->assertNotFound();

        $this->assertSame(MembershipStatus::Invited, $invitation->fresh()->status);
    }

    public function test_owner_admin_can_change_role_and_suspend_another_member(): void
    {
        [$owner, $academy] = $this->owner();
        $member = AcademyMembership::factory()->for($academy)->asRole(AcademyRole::Coach)->create();
        $branch = Branch::factory()->for($academy)->create();

        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.team.update', $member), [
                'role' => AcademyRole::FinanceOfficer->value,
                'status' => MembershipStatus::Suspended->value,
                'branch_ids' => [$branch->id],
            ])
            ->assertRedirect();

        $this->assertSame(AcademyRole::FinanceOfficer, $member->fresh()->role);
        $this->assertSame(MembershipStatus::Suspended, $member->fresh()->status);
        $this->assertSame([$branch->id], $member->branches()->pluck('branches.id')->all());
        $this->assertDatabaseHas(AuditLog::class, [
            'academy_id' => $academy->id,
            'actor_user_id' => $owner->id,
            'action' => 'academy.member.updated',
        ]);
    }

    public function test_staff_cannot_be_assigned_to_another_academys_branch(): void
    {
        [$owner, $academy] = $this->owner();
        $member = AcademyMembership::factory()->for($academy)->asRole(AcademyRole::Coach)->create();
        $foreignBranch = Branch::factory()->create();

        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.team.update', $member), [
                'role' => AcademyRole::Coach->value,
                'status' => MembershipStatus::Active->value,
                'branch_ids' => [$foreignBranch->id],
            ])
            ->assertSessionHasErrors(['branch_ids.0']);

        $this->assertCount(0, $member->branches);
    }

    public function test_owner_admin_role_is_always_academy_wide(): void
    {
        [$owner, $academy] = $this->owner();
        $member = AcademyMembership::factory()->for($academy)->asRole(AcademyRole::Coach)->create();
        $branch = Branch::factory()->for($academy)->create();
        $member->branches()->attach($branch->id, ['academy_id' => $academy->id]);

        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.team.update', $member), [
                'role' => AcademyRole::OwnerAdmin->value,
                'status' => MembershipStatus::Active->value,
                'branch_ids' => [$branch->id],
            ])
            ->assertRedirect();

        $this->assertCount(0, $member->fresh()->branches);
        $this->assertTrue($member->fresh()->canAccessBranch($branch));
    }

    public function test_non_admin_cannot_manage_team(): void
    {
        $academy = Academy::factory()->create();
        $coach = User::factory()->create();
        AcademyMembership::factory()->for($coach)->for($academy)->asRole(AcademyRole::Coach)->create();

        $this->actingAs($coach)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.team.index'))
            ->assertForbidden();

        $this->actingAs($coach)
            ->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.team.store'), [
                'email' => User::factory()->create()->email,
                'role' => AcademyRole::Coach->value,
            ])
            ->assertForbidden();

        $this->actingAs($coach)
            ->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.team.create'), [
                'name' => 'Unauthorized User',
                'email' => 'unauthorized@example.test',
                'role' => AcademyRole::Coach->value,
                'password' => 'Temporary-Password-123!',
                'password_confirmation' => 'Temporary-Password-123!',
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_update_membership_from_another_academy(): void
    {
        [$owner, $academy] = $this->owner();
        $foreignMembership = AcademyMembership::factory()->create();

        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.team.update', $foreignMembership), [
                'role' => AcademyRole::Coach->value,
                'status' => MembershipStatus::Suspended->value,
            ])
            ->assertNotFound();
    }

    public function test_admin_cannot_change_their_own_access(): void
    {
        [$owner, $academy, $membership] = $this->owner();

        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.team.update', $membership), [
                'role' => AcademyRole::Coach->value,
                'status' => MembershipStatus::Suspended->value,
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(AcademyRole::OwnerAdmin, $membership->fresh()->role);
        $this->assertSame(MembershipStatus::Active, $membership->fresh()->status);
    }

    public function test_invite_allows_a_person_without_an_existing_account(): void
    {
        Notification::fake();
        [$owner, $academy] = $this->owner();

        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.team.store'), [
                'email' => 'not-registered@example.test',
                'role' => AcademyRole::Coach->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount(AcademyMembership::class, 1);
        $this->assertDatabaseHas(AcademyInvitation::class, [
            'academy_id' => $academy->id,
            'email' => 'not-registered@example.test',
            'role' => AcademyRole::Coach->value,
        ]);
    }

    /** @return array{User, Academy, AcademyMembership} */
    private function owner(): array
    {
        $owner = User::factory()->create();
        $academy = Academy::factory()->for($owner, 'creator')->create();
        $membership = AcademyMembership::factory()->for($owner)->for($academy)->create();

        return [$owner, $academy, $membership];
    }
}
