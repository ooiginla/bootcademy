<?php

namespace App\Payments\Contracts;

interface PaymentProvider
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array{subaccount: string, transaction_charge: int}|null  $split  sends the payment to an academy
     *                                                                          subaccount; the main account keeps transaction_charge and pays the provider fee from it
     * @return array{access_code: string, reference: string}
     */
    public function initialize(string $email, int $amountMinor, string $currency, string $reference, array $metadata = [], ?array $split = null): array;

    /** @return array<string, mixed> */
    public function verify(string $reference): array;

    /** @return list<array{code: string, name: string}> */
    public function banks(): array;

    /** Returns the account holder's name as held by the bank. */
    public function resolveAccount(string $accountNumber, string $bankCode): string;

    /**
     * Creates the academy's settlement subaccount, or updates it when $subaccountCode is given.
     *
     * @return array{subaccount_code: string, active: bool}
     */
    public function saveSubaccount(?string $subaccountCode, string $businessName, string $bankCode, string $accountNumber, ?string $email, bool $active = true): array;
}
