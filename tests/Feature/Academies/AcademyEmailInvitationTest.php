<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyInvitationStatus;
use App\Enums\AcademyRole;
use App\Enums\MembershipStatus;
use App\Models\Academy;
use App\Models\AcademyInvitation;
use App\Models\AcademyMembership;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AcademyEmailInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_person_can_create_a_personal_account_from_one_time_invitation_link(): void
    {
        [$invitation, $token] = $this->invitation('new.member@example.test');
        $branch = Branch::factory()->for($invitation->academy)->create();
        $invitation->branches()->attach($branch->id, ['academy_id' => $invitation->academy_id]);

        $this->get(route('academy.invites.show', $token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('academies/accept-invitation')
                ->where('invitation.email', 'new.member@example.test')
                ->where('invitation.academy_name', $invitation->academy->name)
                ->where('existing_account', false)
            );

        $this->post(route('academy.invites.accept', $token), [
            'name' => 'New Team Member',
            'password' => 'Secure-password-123',
            'password_confirmation' => 'Secure-password-123',
        ])->assertRedirect(route('dashboard'))
            ->assertSessionHas('active_academy_id', $invitation->academy_id);

        $user = User::query()->where('email', 'new.member@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas(AcademyMembership::class, [
            'academy_id' => $invitation->academy_id,
            'user_id' => $user->id,
            'role' => AcademyRole::Coach->value,
            'status' => MembershipStatus::Active->value,
        ]);
        $membership = AcademyMembership::query()
            ->where('academy_id', $invitation->academy_id)
            ->where('user_id', $user->id)
            ->sole();
        $this->assertSame([$branch->id], $membership->branches()->pluck('branches.id')->all());
        $this->assertDatabaseHas(AcademyInvitation::class, [
            'id' => $invitation->id,
            'status' => AcademyInvitationStatus::Accepted->value,
            'accepted_by_user_id' => $user->id,
        ]);
        $this->assertDatabaseHas(AuditLog::class, [
            'academy_id' => $invitation->academy_id,
            'actor_user_id' => $user->id,
            'action' => 'academy.invitation.accepted',
        ]);

        $this->get(route('academy.invites.show', $token))->assertStatus(410);
    }

    public function test_raw_invitation_token_is_never_stored(): void
    {
        [$invitation, $token] = $this->invitation('private@example.test');

        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
    }

    public function test_existing_account_must_sign_in_before_accepting(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.test']);
        [$invitation, $token] = $this->invitation($user->email);

        $this->get(route('academy.invites.show', $token))
            ->assertOk()
            ->assertSessionHas('url.intended', route('academy.invites.show', $token));

        $this->post(route('academy.invites.accept', $token))->assertForbidden();

        $this->actingAs($user)
            ->post(route('academy.invites.accept', $token))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas(AcademyMembership::class, [
            'academy_id' => $invitation->academy_id,
            'user_id' => $user->id,
            'status' => MembershipStatus::Active->value,
        ]);
    }

    public function test_signed_in_different_user_cannot_claim_invitation(): void
    {
        [$invitation, $token] = $this->invitation('intended@example.test');

        $this->actingAs(User::factory()->create())
            ->post(route('academy.invites.accept', $token), [
                'name' => 'Attacker',
                'password' => 'Secure-password-123',
                'password_confirmation' => 'Secure-password-123',
            ])
            ->assertForbidden();

        $this->assertSame(AcademyInvitationStatus::Pending, $invitation->fresh()->status);
        $this->assertDatabaseMissing(User::class, ['email' => 'intended@example.test']);
    }

    public function test_expired_or_unknown_link_cannot_be_used(): void
    {
        [$invitation, $token] = $this->invitation('expired@example.test');
        $invitation->update(['expires_at' => now()->subMinute()]);

        $this->get(route('academy.invites.show', $token))->assertStatus(410);
        $this->get(route('academy.invites.show', Str::random(64)))->assertNotFound();
    }

    /** @return array{AcademyInvitation, string} */
    private function invitation(string $email): array
    {
        $owner = User::factory()->create();
        $academy = Academy::factory()->for($owner, 'creator')->create();
        AcademyMembership::factory()->for($owner)->for($academy)->create();
        $token = Str::random(64);
        $invitation = AcademyInvitation::factory()->for($academy)->create([
            'email' => $email,
            'token_hash' => hash('sha256', $token),
            'invited_by_user_id' => $owner->id,
            'role' => AcademyRole::Coach,
        ]);

        return [$invitation, $token];
    }
}
