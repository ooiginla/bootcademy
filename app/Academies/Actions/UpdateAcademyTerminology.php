<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;

class UpdateAcademyTerminology
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Academy $academy, User $actor, string $singular, string $plural, string $staffSingular, string $staffPlural, Request $request): void
    {
        $before = [
            'singular' => $academy->member_label_singular,
            'plural' => $academy->member_label_plural,
            'staff_singular' => $academy->staff_label_singular,
            'staff_plural' => $academy->staff_label_plural,
        ];
        $academy->update([
            'member_label_singular' => $singular,
            'member_label_plural' => $plural,
            'staff_label_singular' => $staffSingular,
            'staff_label_plural' => $staffPlural,
        ]);
        $this->audit->record('academy.terminology.updated', $actor, $academy, $academy, [
            'before' => $before,
            'after' => ['singular' => $singular, 'plural' => $plural, 'staff_singular' => $staffSingular, 'staff_plural' => $staffPlural],
        ], $request);
    }
}
