<?php

namespace App\Payments;

use App\Payments\Contracts\PaymentProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackPaymentProvider implements PaymentProvider
{
    public function initialize(string $email, int $amountMinor, string $currency, string $reference, array $metadata = [], ?array $split = null): array
    {
        $payload = [
            'email' => $email,
            'amount' => $amountMinor,
            'currency' => $currency,
            'reference' => $reference,
            'metadata' => $metadata,
        ];
        if ($split !== null) {
            // The subaccount receives amount - transaction_charge; the main account bears Paystack's fee.
            $payload += [
                'subaccount' => $split['subaccount'],
                'transaction_charge' => $split['transaction_charge'],
                'bearer' => 'account',
            ];
        }
        $response = $this->client()->post($this->baseUrl().'/transaction/initialize', $payload);
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

    public function banks(): array
    {
        return array_values(Cache::remember('paystack:banks:nigeria', now()->addDay(), function (): array {
            $response = $this->client()->get($this->baseUrl().'/bank', ['country' => 'nigeria', 'currency' => 'NGN', 'perPage' => 200]);
            if (! $response->successful() || $response->json('status') !== true || ! is_array($response->json('data'))) {
                throw new RuntimeException('Paystack could not load the bank list. Please try again.');
            }

            return collect($response->json('data'))
                ->filter(fn ($bank): bool => is_array($bank) && is_string($bank['code'] ?? null) && is_string($bank['name'] ?? null)
                    && ($bank['active'] ?? true) !== false)
                ->map(fn (array $bank): array => ['code' => $bank['code'], 'name' => $bank['name']])
                ->unique('code')->sortBy('name')->values()->all();
        }));
    }

    public function resolveAccount(string $accountNumber, string $bankCode): string
    {
        $response = $this->client()->get($this->baseUrl().'/bank/resolve', [
            'account_number' => $accountNumber, 'bank_code' => $bankCode,
        ]);
        $name = $response->json('data.account_name');
        if (! $response->successful() || $response->json('status') !== true || ! is_string($name) || trim($name) === '') {
            throw new RuntimeException($this->message($response->json('message'), 'Paystack could not confirm this account number with the bank.'));
        }

        return trim($name);
    }

    public function saveSubaccount(?string $subaccountCode, string $businessName, string $bankCode, string $accountNumber, ?string $email, bool $active = true): array
    {
        $payload = array_filter([
            'business_name' => mb_substr($businessName, 0, 100),
            'settlement_bank' => $bankCode,
            'account_number' => $accountNumber,
            // Each payment sets the platform's share with transaction_charge, so the default share is 0%.
            'percentage_charge' => 0,
            'primary_contact_email' => $email,
            'description' => 'Bootcademy academy settlement account',
        ], fn ($value): bool => $value !== null);
        $response = $subaccountCode === null
            ? $this->client()->post($this->baseUrl().'/subaccount', $payload)
            : $this->client()->put($this->baseUrl().'/subaccount/'.rawurlencode($subaccountCode), $payload + ['active' => $active]);
        $code = $response->json('data.subaccount_code') ?? $subaccountCode;
        if (! $response->successful() || $response->json('status') !== true || ! is_string($code) || $code === '') {
            throw new RuntimeException($this->message($response->json('message'), 'Paystack could not save the settlement account.'));
        }

        return ['subaccount_code' => $code, 'active' => $response->json('data.active') !== false && $active];
    }

    private function message(mixed $providerMessage, string $fallback): string
    {
        $safe = is_string($providerMessage) ? trim(strip_tags($providerMessage)) : '';

        return $safe !== '' ? 'Paystack: '.mb_substr($safe, 0, 240) : $fallback;
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
