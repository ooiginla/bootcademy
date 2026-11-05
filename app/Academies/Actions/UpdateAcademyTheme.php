<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\User;
use App\Platform\AuditLogger;
use App\Support\AccessibleColor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpdateAcademyTheme
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(
        Academy $academy,
        User $actor,
        string $primaryColor,
        string $secondaryColor,
        Request $request,
    ): void {
        $primary = AccessibleColor::normalize($primaryColor);
        $secondary = AccessibleColor::normalize($secondaryColor);
        $previous = [
            'primary_color' => $academy->primary_color,
            'secondary_color' => $academy->secondary_color,
        ];

        DB::transaction(function () use ($academy, $actor, $primary, $secondary, $previous, $request): void {
            $academy->update([
                'primary_color' => $primary,
                'secondary_color' => $secondary,
            ]);

            $this->audit->record(
                'academy.theme.updated',
                $actor,
                $academy,
                $academy,
                ['before' => $previous, 'after' => ['primary_color' => $primary, 'secondary_color' => $secondary]],
                $request,
            );
        });
    }
}
