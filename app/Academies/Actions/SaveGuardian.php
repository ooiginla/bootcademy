<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\Guardian;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SaveGuardian
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(Academy $academy, User $actor, array $data, Request $request, ?Guardian $guardian = null): Guardian
    {
        $guardian ??= new Guardian(['academy_id' => $academy->id]);
        abort_unless($guardian->academy_id === $academy->id, 404);
        $wasExisting = $guardian->exists;
        $signature = Arr::pull($data, 'signature');
        $oldSignaturePath = $guardian->signature_path;
        $newSignaturePath = null;

        try {
            if ($signature instanceof UploadedFile) {
                $storedPath = $signature->store("academies/{$academy->id}/guardians/signatures", 'local');
                throw_if($storedPath === false, RuntimeException::class, 'The guardian signature could not be stored.');
                $newSignaturePath = $storedPath;
                $data['signature_path'] = $storedPath;
            }

            $guardian->fill($data);
            $guardian->save();
        } catch (Throwable $exception) {
            if ($newSignaturePath !== null) {
                Storage::disk('local')->delete($newSignaturePath);
            }

            throw $exception;
        }

        if ($newSignaturePath !== null && $oldSignaturePath !== null) {
            Storage::disk('local')->delete($oldSignaturePath);
        }

        $this->audit->record(
            $wasExisting ? 'academy.guardian.updated' : 'academy.guardian.created',
            $actor,
            $academy,
            $guardian,
            ['changed_fields' => array_keys($guardian->getChanges())],
            $request,
        );

        return $guardian;
    }
}
