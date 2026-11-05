<?php

namespace App\Academies\Actions;

use App\Enums\AcademyInvitationStatus;
use App\Enums\AcademyRole;
use App\Enums\MembershipStatus;
use App\Models\Academy;
use App\Models\AcademyInvitation;
use App\Models\User;
use App\Notifications\AcademyInvitationNotification;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteAcademyMember
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param list<int> $branchIds */
    public function handle(
        Academy $academy,
        User $inviter,
        string $email,
        AcademyRole $role,
        array $branchIds,
        Request $request,
    ): AcademyInvitation {
        $email = Str::lower(trim($email));
        $token = Str::random(64);

        $invitation = DB::transaction(function () use ($academy, $inviter, $email, $role, $branchIds, $token, $request): AcademyInvitation {
            $existingUser = User::query()->where('email', $email)->first();

            if ($existingUser && $academy->memberships()
                ->where('user_id', $existingUser->id)
                ->whereIn('status', [MembershipStatus::Active->value, MembershipStatus::Invited->value])
                ->exists()) {
                throw ValidationException::withMessages(['email' => 'That person is already a member or has a pending invitation.']);
            }

            if ($academy->invitations()
                ->where('email', $email)
                ->where('status', AcademyInvitationStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages(['email' => 'A valid invitation is already pending for that email address.']);
            }

            $invitation = $academy->invitations()->create([
                'email' => $email,
                'role' => $role,
                'status' => AcademyInvitationStatus::Pending,
                'token_hash' => hash('sha256', $token),
                'invited_by_user_id' => $inviter->id,
                'expires_at' => now()->addDays(max(1, (int) config('academies.invitation_ttl_days'))),
            ]);
            $invitation->branches()->sync(collect($branchIds)->mapWithKeys(
                fn (int $branchId): array => [$branchId => ['academy_id' => $academy->id]],
            )->all());

            $this->audit->record('academy.invitation.created', $inviter, $academy, $invitation, [
                'email' => $email,
                'role' => $role->value,
                'branch_ids' => $branchIds,
                'expires_at' => $invitation->expires_at->toAtomString(),
            ], $request);

            return $invitation;
        });

        $invitation->loadMissing(['academy', 'inviter']);
        Notification::route('mail', $email)
            ->notify(new AcademyInvitationNotification($invitation, $token));

        return $invitation;
    }
}
