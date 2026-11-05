<?php

namespace App\Academies;

use App\Enums\AcademyRole;
use App\Enums\MembershipStatus;
use App\Models\Academy;
use App\Models\AcademyMembership;
use LogicException;

class AcademyContext
{
    private ?Academy $academy = null;

    private ?AcademyMembership $membership = null;

    private bool $supportMode = false;

    public function set(AcademyMembership $membership): void
    {
        $this->membership = $membership;
        $this->academy = $membership->academy;
        $this->supportMode = false;
    }

    public function setSupport(Academy $academy): void
    {
        $this->academy = $academy;
        $this->membership = null;
        $this->supportMode = true;
    }

    public function hasAcademy(): bool
    {
        return $this->academy !== null;
    }

    public function academy(): Academy
    {
        return $this->academy ?? throw new LogicException('No active academy has been resolved.');
    }

    public function membership(): AcademyMembership
    {
        return $this->membership ?? throw new LogicException('No active academy membership has been resolved.');
    }

    public function membershipOrNull(): ?AcademyMembership
    {
        return $this->membership;
    }

    public function isSupportMode(): bool
    {
        return $this->supportMode;
    }

    public function canManageAcademy(): bool
    {
        return $this->supportMode || $this->membership?->canManageAcademy() === true;
    }

    public function canManageFinance(): bool
    {
        return $this->supportMode
            || ($this->membership?->status === MembershipStatus::Active
                && in_array($this->membership->role, [AcademyRole::OwnerAdmin, AcademyRole::FinanceOfficer], true));
    }

    public function canViewAuditLog(): bool
    {
        return $this->supportMode || $this->membership?->canManageAcademy() === true;
    }

    public function id(): int
    {
        return $this->academy()->id;
    }
}
