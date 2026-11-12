<?php

namespace App\Http\Controllers\Academies;

use App\Enums\AcademyInvitationStatus;
use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademyInvitation;
use App\Models\AcademyMembership;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AcceptAcademyInvitationController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $invitation = $this->findAcceptableInvitation($token);
        $invitation->loadMissing('academy:id,name,academy_type,staff_label_singular');
        $existingUser = User::query()->where('email', $invitation->email)->first();
        $signedInUser = $request->user();

        if ($existingUser && ! $signedInUser) {
            $request->session()->put('url.intended', route('academy.invites.show', $token));
        }

        return Inertia::render('academies/accept-invitation', [
            'token' => $token,
            'invitation' => [
                'academy_name' => $invitation->academy->name,
                'academy_type' => $invitation->academy->academy_type,
                'email' => $invitation->email,
                'role_label' => $invitation->academy->roleLabel($invitation->role),
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
            'existing_account' => $existingUser !== null,
            'authenticated_as_invitee' => $existingUser !== null && $signedInUser?->is($existingUser) === true,
            'authenticated_as_different_user' => $signedInUser !== null
                && ($existingUser === null || ! $signedInUser->is($existingUser)),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function accept(
        Request $request,
        string $token,
        AuditLogger $audit,
    ): RedirectResponse {
        $invitation = $this->findAcceptableInvitation($token);
        $existingUser = User::query()->where('email', $invitation->email)->first();

        if ($existingUser) {
            abort_unless($request->user()?->is($existingUser) === true, 403);
            $user = $existingUser;
        } else {
            abort_if($request->user() !== null, 403);
            $validated = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', Password::default(), 'confirmed'],
            ])->validate();

            $user = new User([
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => $validated['password'],
            ]);
            $user->forceFill(['email_verified_at' => now()]);
        }

        DB::transaction(function () use ($invitation, $user, $request, $audit): void {
            $lockedInvitation = AcademyInvitation::query()->lockForUpdate()->findOrFail($invitation->id);

            if (! $lockedInvitation->isAcceptable()) {
                throw ValidationException::withMessages(['invitation' => 'This invitation is no longer available.']);
            }

            if (! $user->exists) {
                $user->save();
            }

            // A removed member keeps their membership row; re-inviting restores it.
            $membership = AcademyMembership::withTrashed()
                ->where('academy_id', $lockedInvitation->academy_id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($membership?->trashed()) {
                $membership->restore();
                $membership->coachProfile()->withTrashed()->first()?->restore();
            }

            if ($membership?->status === MembershipStatus::Active) {
                throw ValidationException::withMessages(['invitation' => 'This account already belongs to the academy.']);
            }

            $membership ??= new AcademyMembership([
                'academy_id' => $lockedInvitation->academy_id,
                'user_id' => $user->id,
            ]);
            $membership->fill([
                'role' => $lockedInvitation->role,
                'status' => MembershipStatus::Active,
                'invited_by_user_id' => $lockedInvitation->invited_by_user_id,
                'joined_at' => now(),
            ])->save();
            $membership->branches()->sync($lockedInvitation->branches()->pluck('branches.id')->mapWithKeys(
                fn (int $branchId): array => [$branchId => ['academy_id' => $lockedInvitation->academy_id]],
            )->all());

            $lockedInvitation->update([
                'status' => AcademyInvitationStatus::Accepted,
                'accepted_by_user_id' => $user->id,
                'accepted_at' => now(),
            ]);

            $audit->record(
                'academy.invitation.accepted',
                $user,
                $lockedInvitation->academy,
                $lockedInvitation,
                ['membership_id' => $membership->id],
                $request,
            );
        });

        if (! Auth::check()) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        $request->session()->put('active_academy_id', $invitation->academy_id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Welcome to '.$invitation->academy->name.'.',
        ]);

        return to_route('dashboard');
    }

    private function findAcceptableInvitation(string $token): AcademyInvitation
    {
        $invitation = AcademyInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        abort_unless($invitation->isAcceptable(), 410, 'This invitation has expired or has already been used.');

        return $invitation;
    }
}
