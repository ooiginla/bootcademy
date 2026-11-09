<?php

namespace App\Payments;

use Illuminate\Http\Request;

class PaystackWebhookSignature
{
    public function valid(Request $request): bool
    {
        $raw = $request->getContent();
        $modern = $request->header('Paystack-Signature');
        $webhookSecret = config('services.paystack.webhook_secret');
        if (is_string($modern) && $modern !== '' && is_string($webhookSecret) && $webhookSecret !== '') {
            return $this->validModern($modern, $raw, $webhookSecret);
        }

        $legacy = $request->header('x-paystack-signature');
        $secret = config('services.paystack.secret_key');
        if (! is_string($legacy) || ! preg_match('/^[0-9a-f]{128}$/i', $legacy) || ! is_string($secret) || $secret === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $raw, $secret), strtolower($legacy));
    }

    private function validModern(string $header, string $raw, string $secret): bool
    {
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key === 't' && is_string($value) && ctype_digit($value)) {
                $timestamp = (int) $value;
            }
            if ($key === 'v1' && is_string($value)) {
                $signatures[] = str_contains($value, ':') ? explode(':', $value, 2)[1] : $value;
            }
        }
        if ($timestamp === null || abs(time() - $timestamp) > 300) {
            return false;
        }
        $expected = hash_hmac('sha512', $timestamp.'.'.$raw, $secret);

        return collect($signatures)->contains(fn (string $signature): bool => hash_equals($expected, $signature));
    }
}
