<?php

namespace App\Http\Middleware;

use App\Models\AcademyApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAcademyApiKey
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = $request->bearerToken();
        if ($secret === null || ! str_starts_with($secret, 'aca_')) {
            return $this->unauthorized();
        }

        $apiKey = AcademyApiKey::query()
            ->with('academy')
            ->where('secret_hash', hash('sha256', $secret))
            ->whereNull('revoked_at')
            ->first();
        if ($apiKey === null) {
            return $this->unauthorized();
        }

        $apiKey->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('academy_api_key', $apiKey);
        $request->attributes->set('api_academy', $apiKey->academy);

        return $next($request);
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'message' => 'A valid academy API bearer token is required.',
        ], 401);
    }
}
