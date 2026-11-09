<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpdateAcademyProfile
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Academy $academy, User $actor, string $name, Request $request): void
    {
        $previousName = $academy->name;

        DB::transaction(function () use ($academy, $actor, $name, $previousName, $request): void {
            $academy->update(['name' => $name]);

            $this->audit->record(
                'academy.profile.updated',
                $actor,
                $academy,
                $academy,
                ['before' => ['name' => $previousName], 'after' => ['name' => $name]],
                $request,
            );
        });
    }
}
