<?php

namespace App\Http\Controllers;

use App\Models\AcademyResource;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicAcademyResourceController extends Controller
{
    public function __invoke(AcademyResource $resource): StreamedResponse
    {
        abort_unless(Storage::disk($resource->storage_disk)->exists($resource->storage_path), 404);

        $disposition = str_starts_with($resource->mime_type, 'image/') || $resource->mime_type === 'application/pdf'
            ? 'inline'
            : 'attachment';

        return Storage::disk($resource->storage_disk)->response(
            $resource->storage_path,
            $resource->original_name,
            [
                'Content-Type' => $resource->mime_type,
                'Cache-Control' => 'max-age=3600, public',
                'X-Content-Type-Options' => 'nosniff',
            ],
            $disposition,
        );
    }
}
