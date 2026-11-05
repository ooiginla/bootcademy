<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\Branch;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaveBranch
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $attributes */
    public function handle(
        Academy $academy,
        User $actor,
        array $attributes,
        Request $request,
        ?Branch $branch = null,
    ): Branch {
        return DB::transaction(function () use ($academy, $actor, $attributes, $request, $branch): Branch {
            $before = $branch?->only(array_keys($attributes));
            $branch ??= new Branch(['academy_id' => $academy->id]);
            abort_unless($branch->academy_id === $academy->id, 404);

            $branch->fill($attributes);
            $branch->save();

            $this->audit->record(
                $before ? 'academy.branch.updated' : 'academy.branch.created',
                $actor,
                $academy,
                $branch,
                $before ? ['before' => $before, 'after' => $branch->only(array_keys($attributes))] : [],
                $request,
            );

            return $branch;
        });
    }
}
