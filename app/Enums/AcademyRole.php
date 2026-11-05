<?php

namespace App\Enums;

enum AcademyRole: string
{
    case OwnerAdmin = 'owner_admin';
    case FinanceOfficer = 'finance_officer';
    case BranchManager = 'branch_manager';
    case Coach = 'coach';
    case Guardian = 'guardian';
    case ReadOnlyAuditor = 'read_only_auditor';

    public function label(): string
    {
        return match ($this) {
            self::OwnerAdmin => 'Academy Owner / Admin',
            self::FinanceOfficer => 'Finance Officer',
            self::BranchManager => 'Branch Manager',
            self::Coach => 'Coach / Instructor',
            self::Guardian => 'Guardian',
            self::ReadOnlyAuditor => 'Read-only Auditor',
        };
    }

    public function managesAcademy(): bool
    {
        return $this === self::OwnerAdmin;
    }

    /** @return list<self> */
    public static function staffRoles(): array
    {
        return [
            self::OwnerAdmin,
            self::FinanceOfficer,
            self::BranchManager,
            self::Coach,
            self::ReadOnlyAuditor,
        ];
    }
}
