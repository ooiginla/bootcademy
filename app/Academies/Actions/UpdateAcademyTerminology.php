<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;

class UpdateAcademyTerminology
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Academy $academy, User $actor, string $singular, string $plural, string $staffSingular, string $staffPlural, string $guardianSingular, string $guardianPlural, bool $collectGuardianInformation, Request $request): void
    {
        $before = [
            'singular' => $academy->member_label_singular,
            'plural' => $academy->member_label_plural,
            'staff_singular' => $academy->staff_label_singular,
            'staff_plural' => $academy->staff_label_plural,
            'guardian_singular' => $academy->guardian_label_singular,
            'guardian_plural' => $academy->guardian_label_plural,
            'collect_guardian_information' => $academy->collect_guardian_information,
        ];
        $academy->update([
            'member_label_singular' => $singular,
            'member_label_plural' => $plural,
            'staff_label_singular' => $staffSingular,
            'staff_label_plural' => $staffPlural,
            'guardian_label_singular' => $guardianSingular,
            'guardian_label_plural' => $guardianPlural,
            'collect_guardian_information' => $collectGuardianInformation,
        ]);
        $this->audit->record('academy.terminology.updated', $actor, $academy, $academy, [
            'before' => $before,
            'after' => [
                'singular' => $singular, 'plural' => $plural,
                'staff_singular' => $staffSingular, 'staff_plural' => $staffPlural,
                'guardian_singular' => $guardianSingular, 'guardian_plural' => $guardianPlural,
                'collect_guardian_information' => $collectGuardianInformation,
            ],
        ], $request);
    }
}
