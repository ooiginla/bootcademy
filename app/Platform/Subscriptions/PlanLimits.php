<?php

namespace App\Platform\Subscriptions;

use App\Enums\AcademyInvitationStatus;
use App\Enums\AcademyRole;
use App\Enums\EnrollmentStatus;
use App\Enums\MembershipStatus;
use App\Models\Academy;
use App\Models\Enrollment;
use App\Models\Player;
use App\Platform\AcademyEntitlements;
use Illuminate\Validation\ValidationException;

/**
 * Plan allowances for active players, branches and staff accounts.
 *
 * Limits only stop new usage. Records over the limit after a downgrade stay as they are.
 * A null limit means no limit.
 */
class PlanLimits
{
    /** @return array{active_players: int, branches: int, staff_accounts: int} */
    public function usage(Academy $academy): array
    {
        return [
            'active_players' => $this->activePlayers($academy),
            'branches' => $academy->branches()->where('is_active', true)->count(),
            'staff_accounts' => $this->staffAccounts($academy),
        ];
    }

    /** @return array{active_players: int|null, branches: int|null, staff_accounts: int|null} */
    public function limits(Academy $academy): array
    {
        $plan = AcademyEntitlements::for($academy)->plan();

        return [
            'active_players' => $plan?->active_player_limit,
            'branches' => $plan?->branch_limit,
            'staff_accounts' => $plan?->staff_account_limit,
        ];
    }

    public function ensureCanAddBranch(Academy $academy, string $field = 'name'): void
    {
        $limit = $this->limits($academy)['branches'];
        if ($limit !== null && $academy->branches()->where('is_active', true)->count() >= $limit) {
            throw ValidationException::withMessages([$field => $this->message($academy, $limit, $limit === 1 ? 'active branch' : 'active branches')]);
        }
    }

    public function ensureCanAddStaff(Academy $academy, string $field = 'email'): void
    {
        $limit = $this->limits($academy)['staff_accounts'];
        if ($limit !== null && $this->staffAccounts($academy) >= $limit) {
            throw ValidationException::withMessages([$field => $this->message($academy, $limit, $limit === 1 ? 'staff account' : 'staff accounts').' Pending invitations count towards this.']);
        }
    }

    /** Call before an enrolment becomes active. Players who already have an active enrolment are not counted again. */
    public function ensureCanActivatePlayer(Academy $academy, Player $player, ?Enrollment $enrollment = null, string $field = 'programme_id'): void
    {
        $limit = $this->limits($academy)['active_players'];
        if ($limit === null) {
            return;
        }
        $alreadyActive = Enrollment::query()
            ->where('academy_id', $academy->id)
            ->where('player_id', $player->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->when($enrollment?->exists, fn ($query) => $query->whereKeyNot($enrollment?->id))
            ->exists();
        if (! $alreadyActive && $this->activePlayers($academy, $player->id) >= $limit) {
            throw ValidationException::withMessages([$field => $this->message($academy, $limit, 'active players')]);
        }
    }

    private function activePlayers(Academy $academy, ?int $exceptPlayerId = null): int
    {
        return Enrollment::query()
            ->where('academy_id', $academy->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->whereHas('player')
            ->when($exceptPlayerId, fn ($query) => $query->where('player_id', '!=', $exceptPlayerId))
            ->distinct()
            ->count('player_id');
    }

    private function staffAccounts(Academy $academy): int
    {
        $staffRoles = array_map(fn (AcademyRole $role): string => $role->value, AcademyRole::staffRoles());

        return $academy->memberships()
            ->where('status', MembershipStatus::Active->value)
            ->whereIn('role', $staffRoles)
            ->count()
            + $academy->invitations()
                ->where('status', AcademyInvitationStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->whereIn('role', $staffRoles)
                ->count();
    }

    private function message(Academy $academy, int $limit, string $what): string
    {
        $plan = AcademyEntitlements::for($academy)->plan()->name ?? 'current';

        return "Your {$plan} plan allows {$limit} {$what}. Upgrade your subscription to add more.";
    }
}
