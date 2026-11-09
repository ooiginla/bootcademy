<?php

namespace App\Payments;

use App\Payments\Contracts\PaymentProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackPaymentProvider implements PaymentProvider
{
    public function initialize(string $email, int $amountMinor, string $currency, string $reference, array $metadata = []): array
    {
        $response = $this->client()->post($this->baseUrl().'/transaction/initialize', [
            'email' => $email,
            'amount' => $amountMinor,
            'currency' => $currency,
            'reference' => $reference,
            'metadata' => $metadata,
        ]);
        if (! $response->successful() || $response->json('status') !== true) {
            $providerMessage = $response->json('message');
            $safeMessage = is_string($providerMessage)
                ? trim(strip_tags($providerMessage))
                : '';

            throw new RuntimeException($safeMessage !== ''
                ? 'Paystack: '.mb_substr($safeMessage, 0, 240)
                : 'Paystack could not initialize this payment. Please try again.');
        }
        $accessCode = $response->json('data.access_code');
        $providerReference = $response->json('data.reference');
        if (! is_string($accessCode) || ! is_string($providerReference) || $providerReference !== $reference) {
            throw new RuntimeException('Paystack returned an invalid initialization response.');
        }

        return ['access_code' => $accessCode, 'reference' => $providerReference];
    }

    public function verify(string $reference): array
    {
        $response = $this->client()->get($this->baseUrl().'/transaction/verify/'.rawurlencode($reference));
        if (! $response->successful() || $response->json('status') !== true || ! is_array($response->json('data'))) {
            throw new RuntimeException('Paystack could not verify this payment yet.');
        }

        return $response->json('data');
    }

    private function client(): PendingRequest
    {
        $secret = config('services.paystack.secret_key');
        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('Paystack is not configured.');
        }

        return Http::acceptJson()->asJson()->withToken($secret)->connectTimeout(5)->timeout(15);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/');
    }
}
