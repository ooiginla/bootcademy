<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\AcademyResource;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class StoreAcademyResource
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(
        Academy $academy,
        User $actor,
        UploadedFile $file,
        Request $request,
    ): AcademyResource {
        $publicId = (string) Str::uuid();
        $extension = Str::lower($file->extension());
        $disk = (string) config('filesystems.resource_library_disk', 'local');
        $path = $file->storeAs(
            "academies/{$academy->id}/resources",
            $publicId.'.'.$extension,
            $disk,
        );

        abort_unless(is_string($path), 500, 'The resource could not be stored.');

        try {
            return DB::transaction(function () use ($academy, $actor, $file, $request, $publicId, $extension, $disk, $path): AcademyResource {
                $originalName = mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255);
                if ($originalName === '') {
                    $originalName = 'resource.'.$extension;
                }

                $resource = AcademyResource::query()->create([
                    'public_id' => $publicId,
                    'academy_id' => $academy->id,
                    'uploaded_by_user_id' => $actor->id,
                    'original_name' => $originalName,
                    'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                    'extension' => $extension,
                    'size_bytes' => $file->getSize(),
                    'storage_disk' => $disk,
                    'storage_path' => $path,
                ]);

                $this->audit->record('academy.resource.uploaded', $actor, $academy, $resource, [
                    'name' => $resource->original_name,
                    'mime_type' => $resource->mime_type,
                    'size_bytes' => $resource->size_bytes,
                ], $request);

                return $resource;
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }
}
