<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    public static function toMinor(string $amount): int
    {
        $amount = trim($amount);
        if (preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', $amount) !== 1) {
            throw new InvalidArgumentException('Invalid money amount.');
        }
        [$whole, $decimal] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($decimal, 2, '0');
    }

    public static function fromMinor(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }

    public static function format(string $currency, string $amount): string
    {
        $symbol = match (strtoupper($currency)) {
            'NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€',
            default => strtoupper($currency).' ',
        };

        return $symbol.number_format((float) $amount, 2);
    }
}
