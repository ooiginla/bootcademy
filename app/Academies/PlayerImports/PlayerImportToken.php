<?php

namespace App\Academies\PlayerImports;

use App\Models\Academy;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class PlayerImportToken
{
    public const LIFETIME_HOURS = 24;

    public function issue(Academy $academy, User $user, string $path, string $originalName): string
    {
        return Crypt::encryptString(json_encode([
            'academy_id' => $academy->id,
            'user_id' => $user->id,
            'path' => $path,
            'original_name' => $originalName,
            'expires_at' => now()->addHours(self::LIFETIME_HOURS)->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array{academy_id: int, user_id: int, path: string, original_name: string, expires_at: int} */
    public function resolve(string $token, Academy $academy, User $user): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            abort(404);
        }

        abort_unless(
            is_array($payload)
            && (int) ($payload['academy_id'] ?? 0) === $academy->id
            && (int) ($payload['user_id'] ?? 0) === $user->id
            && is_string($payload['path'] ?? null)
            && str_starts_with($payload['path'], "imports/{$academy->id}/{$user->id}/")
            && is_string($payload['original_name'] ?? null)
            && is_numeric($payload['expires_at'] ?? null),
            404,
        );

        if ((int) $payload['expires_at'] < now()->timestamp) {
            Storage::disk('local')->delete($payload['path']);
            abort(410, 'This import preview has expired. Upload the CSV file again.');
        }

        abort_unless(Storage::disk('local')->exists($payload['path']), 404);

        return [
            'academy_id' => (int) $payload['academy_id'],
            'user_id' => (int) $payload['user_id'],
            'path' => $payload['path'],
            'original_name' => $payload['original_name'],
            'expires_at' => (int) $payload['expires_at'],
        ];
    }
}
