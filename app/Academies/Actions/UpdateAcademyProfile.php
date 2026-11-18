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

    /** @param array{name: string, academy_type: string, country_code: string, timezone: string, currency: string} $data */
    public function handle(Academy $academy, User $actor, array $data, Request $request): void
    {
        $fields = ['name', 'academy_type', 'country_code', 'timezone', 'currency'];
        $before = $academy->only($fields);

        DB::transaction(function () use ($academy, $actor, $data, $before, $request): void {
            $academy->update($data);

            $this->audit->record(
                'academy.profile.updated',
                $actor,
                $academy,
                $academy,
                ['before' => $before, 'after' => $data],
                $request,
            );
        });
    }
}
