<?php

namespace App\Policies;

use App\Academies\AcademyContext;
use App\Enums\AcademyRole;
use App\Enums\MembershipStatus;
use App\Models\Academy;
use App\Models\User;

class AcademyPolicy
{
    /**
     * Academy owner/admins and a Platform Super Admin actively acting as the
     * academy owner have full academy-level management access.
     */
    public function manage(User $user, Academy $academy): bool
    {
        $context = app(AcademyContext::class);

        if ($user->is_platform_super_admin
            && $context->isSupportMode()
            && $context->hasAcademy()
            && $context->id() === $academy->id) {
            return true;
        }

        return $user->academyMemberships()
            ->where('academy_id', $academy->id)
            ->where('status', MembershipStatus::Active->value)
            ->where('role', AcademyRole::OwnerAdmin->value)
            ->exists();
    }
}
