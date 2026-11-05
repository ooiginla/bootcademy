<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateAcademyBankDetails
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, string|null> $data */
    public function handle(Academy $academy, User $actor, array $data, Request $request): void
    {
        DB::transaction(function () use ($academy, $actor, $data, $request): void {
            $academy->update(Arr::only($data, ['bank_name', 'bank_account_name', 'bank_account_number']));
            $this->audit->record('academy.bank_details.updated', $actor, $academy, $academy, [
                'configured' => $academy->bank_account_number !== null,
            ], $request);
        });
    }
}
