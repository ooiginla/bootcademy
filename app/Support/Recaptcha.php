<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifies Google reCAPTCHA v3 tokens on the server.
 *
 * Verification is skipped while no secret key is configured. When Google
 * cannot be reached the token is treated as failed, so a bot cannot get
 * through by waiting for an outage.
 */
class Recaptcha
{
    public function enabled(): bool
    {
        return filled(config('services.recaptcha.secret_key')) && filled(config('services.recaptcha.site_key'));
    }

    public function siteKey(): ?string
    {
        return $this->enabled() ? (string) config('services.recaptcha.site_key') : null;
    }

    public function passes(?string $token, string $action, ?string $ip = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if (! is_string($token) || $token === '' || strlen($token) > 4000) {
            return false;
        }

        try {
            $response = Http::asForm()->connectTimeout(3)->timeout(5)->retry(2, 200, throw: false)
                ->post((string) config('services.recaptcha.verify_url'), array_filter([
                    'secret' => config('services.recaptcha.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (ConnectionException $exception) {
            Log::warning('reCAPTCHA verification could not reach Google.', ['message' => $exception->getMessage()]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('reCAPTCHA verification returned an error status.', ['status' => $response->status()]);

            return false;
        }

        $score = (float) $response->json('score', 0);
        $passed = $response->json('success') === true
            && $response->json('action') === $action
            && $score >= (float) config('services.recaptcha.min_score', 0.5);

        if (! $passed) {
            Log::info('reCAPTCHA rejected a submission.', [
                'action' => $action,
                'reported_action' => $response->json('action'),
                'score' => $score,
                'errors' => $response->json('error-codes'),
            ]);
        }

        return $passed;
    }
}
