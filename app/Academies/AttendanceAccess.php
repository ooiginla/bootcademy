<?php

namespace App\Academies;

use App\Enums\AcademyRole;
use App\Models\TrainingSession;

class AttendanceAccess
{
    public static function allowed(TrainingSession $session, AcademyContext $context): bool
    {
        if ($session->academy_id !== $context->id()) {
            return false;
        }
        if ($context->canManageAcademy()) {
            return true;
        }
        $membership = $context->membershipOrNull();
        if ($membership?->role === AcademyRole::Coach) {
            return $session->coaches()->whereKey($membership->id)->exists();
        }
        if ($membership?->role === AcademyRole::BranchManager) {
            if ($session->all_branches) {
                return true;
            }

            return $session->branches()->whereIn('branches.id', $membership->branches()->pluck('branches.id'))->exists();
        }

        return false;
    }
}
