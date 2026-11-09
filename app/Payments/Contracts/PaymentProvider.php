<?php

namespace App\Payments\Contracts;

interface PaymentProvider
{
    /** @param array<string, mixed> $metadata
     * @return array{access_code: string, reference: string}
     */
    public function initialize(string $email, int $amountMinor, string $currency, string $reference, array $metadata = []): array;

    /** @return array<string, mixed> */
    public function verify(string $reference): array;
}
