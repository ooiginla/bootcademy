<?php

namespace App\Academies\Actions;

use App\Enums\AcademyRole;
use App\Enums\MembershipStatus;
use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreateAcademyMember
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param list<int> $branchIds */
    public function handle(
        Academy $academy,
        User $actor,
        string $name,
        string $email,
        string $password,
        AcademyRole $role,
        array $branchIds,
        Request $request,
    ): AcademyMembership {
        [$user, $membership] = DB::transaction(function () use ($academy, $actor, $name, $email, $password, $role, $branchIds, $request): array {
            $user = User::create([
                'name' => trim($name),
                'email' => mb_strtolower(trim($email)),
                'password' => $password,
                'must_change_password' => true,
            ]);

            $membership = $academy->memberships()->create([
                'user_id' => $user->id,
                'role' => $role,
                'status' => MembershipStatus::Active,
                'invited_by_user_id' => $actor->id,
                'joined_at' => now(),
            ]);
            $membership->branches()->sync(collect($branchIds)->mapWithKeys(
                fn (int $branchId): array => [$branchId => ['academy_id' => $academy->id]],
            )->all());

            $this->audit->record('academy.member.created', $actor, $academy, $membership, [
                'created_user_id' => $user->id,
                'role' => $role->value,
                'branch_ids' => $branchIds,
                'requires_password_change' => true,
            ], $request);

            return [$user, $membership];
        });

        event(new Registered($user));

        return $membership;
    }
}
