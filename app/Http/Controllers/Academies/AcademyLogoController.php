<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Http\Controllers\Controller;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcademyLogoController extends Controller
{
    public function __invoke(AcademyContext $context): StreamedResponse
    {
        $path = $context->academy()->logo_path;
        abort_if($path === null, 404);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
