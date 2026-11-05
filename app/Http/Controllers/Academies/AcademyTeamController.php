<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\CreateAcademyMember;
use App\Academies\Actions\InviteAcademyMember;
use App\Enums\AcademyInvitationStatus;
use App\Enums\AcademyRole;
use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\CreateAcademyMemberRequest;
use App\Http\Requests\Academies\InviteAcademyMemberRequest;
use App\Http\Requests\Academies\UpdateAcademyMemberRequest;
use App\Models\AcademyMembership;
use App\Platform\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AcademyTeamController extends Controller
{
    public function index(AcademyContext $context): Response
    {
        Gate::authorize('manage', $context->academy());

        return Inertia::render('academies/team', [
            'team' => $context->academy()->memberships()
                ->with(['user:id,name,email', 'branches:id,name,is_active'])
                ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'invited' THEN 1 ELSE 2 END")
                ->orderBy('created_at')
                ->get()
                ->map(fn (AcademyMembership $membership): array => [
                    'id' => $membership->id,
                    'user_id' => $membership->user_id,
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                    'role' => $membership->role->value,
                    'role_label' => $context->academy()->roleLabel($membership->role),
                    'status' => $membership->status->value,
                    'is_current_user' => $membership->user_id === request()->user()->id,
                    'branches' => $membership->branches->map(fn ($branch): array => [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'is_active' => $branch->is_active,
                    ])->values(),
                ]),
            'roles' => collect(AcademyRole::staffRoles())->map(fn (AcademyRole $role): array => [
                'value' => $role->value,
                'label' => $context->academy()->roleLabel($role),
            ]),
            'pendingInvitations' => $context->academy()->invitations()
                ->with('branches:id,name,is_active')
                ->where('status', AcademyInvitationStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->latest()
                ->get()
                ->map(fn ($invitation): array => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role_label' => $context->academy()->roleLabel($invitation->role),
                    'expires_at' => $invitation->expires_at->toIso8601String(),
                    'branches' => $invitation->branches->map(fn ($branch): array => [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'is_active' => $branch->is_active,
                    ])->values(),
                ]),
            'branches' => $context->academy()->branches()
                ->orderByDesc('is_active')->orderBy('name')->get(['id', 'name', 'is_active']),
        ]);
    }

    public function store(
        InviteAcademyMemberRequest $request,
        AcademyContext $context,
        InviteAcademyMember $inviteMember,
    ): RedirectResponse {
        if ($request->user()->email === $request->string('email')->toString()) {
            throw ValidationException::withMessages(['email' => 'You already belong to this academy.']);
        }

        $role = $request->enum('role', AcademyRole::class);
        abort_unless($role instanceof AcademyRole, 422);

        $inviteMember->handle(
            $context->academy(),
            $request->user(),
            $request->string('email')->toString(),
            $role,
            $this->branchIds($request->validated(), $role),
            $request,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invitation email sent.']);

        return back();
    }

    public function createMember(
        CreateAcademyMemberRequest $request,
        AcademyContext $context,
        CreateAcademyMember $createMember,
    ): RedirectResponse {
        $role = $request->enum('role', AcademyRole::class);
        abort_unless($role instanceof AcademyRole, 422);

        $createMember->handle(
            $context->academy(),
            $request->user(),
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $role,
            $this->branchIds($request->validated(), $role),
            $request,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Team account created and activated.']);

        return back();
    }

    public function update(
        UpdateAcademyMemberRequest $request,
        AcademyMembership $membership,
        AcademyContext $context,
        AuditLogger $audit,
    ): RedirectResponse {
        if ($membership->user_id === $request->user()->id) {
            throw ValidationException::withMessages(['status' => 'You cannot change your own academy access.']);
        }

        DB::transaction(function () use ($request, $membership, $context, $audit): void {
            $membership->refresh();
            $nextRole = $request->enum('role', AcademyRole::class);
            $nextStatus = $request->enum('status', MembershipStatus::class);
            abort_unless($nextRole instanceof AcademyRole && $nextStatus instanceof MembershipStatus, 422);
            $branchIds = $this->branchIds($request->validated(), $nextRole);

            if ($membership->role === AcademyRole::OwnerAdmin
                && ($nextRole !== AcademyRole::OwnerAdmin || $nextStatus !== MembershipStatus::Active)
                && $context->academy()->memberships()
                    ->where('role', AcademyRole::OwnerAdmin->value)
                    ->where('status', MembershipStatus::Active->value)
                    ->count() <= 1) {
                throw ValidationException::withMessages(['status' => 'Every academy must retain an active Owner/Admin.']);
            }

            $before = [
                'role' => $membership->role->value,
                'status' => $membership->status->value,
                'branch_ids' => $membership->branches()->pluck('branches.id')->all(),
            ];
            $membership->update(['role' => $nextRole, 'status' => $nextStatus]);
            $membership->branches()->sync(collect($branchIds)->mapWithKeys(
                fn (int $branchId): array => [$branchId => ['academy_id' => $context->id()]],
            )->all());

            $audit->record('academy.member.updated', $request->user(), $context->academy(), $membership, [
                'before' => $before,
                'after' => ['role' => $nextRole->value, 'status' => $nextStatus->value, 'branch_ids' => $branchIds],
            ], $request);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Team access updated.']);

        return back();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<int>
     */
    private function branchIds(array $validated, AcademyRole $role): array
    {
        if ($role === AcademyRole::OwnerAdmin) {
            return [];
        }

        $branchIds = $validated['branch_ids'] ?? [];
        abort_unless(is_array($branchIds), 422);

        return array_map(fn (mixed $id): int => (int) $id, array_values($branchIds));
    }
}
