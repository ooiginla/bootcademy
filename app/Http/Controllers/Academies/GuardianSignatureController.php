<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Http\Controllers\Controller;
use App\Models\Guardian;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuardianSignatureController extends Controller
{
    public function __invoke(Guardian $guardian, AcademyContext $context): StreamedResponse
    {
        abort_unless($context->canManageAcademy(), 403);
        abort_unless($guardian->academy_id === $context->id(), 404);
        abort_if($guardian->signature_path === null, 404);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        abort_unless($disk->exists($guardian->signature_path), 404);

        return $disk->response($guardian->signature_path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:",
        ]);
    }
}
