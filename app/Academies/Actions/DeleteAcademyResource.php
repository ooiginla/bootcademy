<?php

namespace App\Academies\Actions;

use App\Models\AcademyResource;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteAcademyResource
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(AcademyResource $resource, User $actor, Request $request): void
    {
        DB::transaction(function () use ($resource, $actor, $request): void {
            $resource->delete();
            $this->audit->record('academy.resource.deleted', $actor, $resource->academy, $resource, [
                'name' => $resource->original_name,
                'public_id' => $resource->public_id,
            ], $request);
        });

        // The database deletion revokes the public URL immediately. A failed
        // object deletion can only leave an unreachable object on private storage.
        Storage::disk($resource->storage_disk)->delete($resource->storage_path);
    }
}
