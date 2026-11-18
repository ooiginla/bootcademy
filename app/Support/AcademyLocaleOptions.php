<?php

namespace App\Support;

use DateTimeZone;
use NumberFormatter;
use ResourceBundle;
use Throwable;

class AcademyLocaleOptions
{
    /** @var list<array{code: string, name: string, timezone: string, currency: string}>|null */
    private static ?array $countryCache = null;

    /** @var list<string> */
    private const EXCLUDED_TERRITORIES = ['EU', 'EZ', 'QO', 'UN', 'XA', 'XB', 'ZZ'];

    /** @var array<string, string> */
    private const TIMEZONE_OVERRIDES = [
        'AU' => 'Australia/Sydney',
        'BR' => 'America/Sao_Paulo',
        'CA' => 'America/Toronto',
        'ID' => 'Asia/Jakarta',
        'MX' => 'America/Mexico_City',
        'RU' => 'Europe/Moscow',
        'US' => 'America/New_York',
    ];

    /**
     * @return list<array{code: string, name: string, timezone: string, currency: string}>
     */
    public static function countries(): array
    {
        if (self::$countryCache !== null) {
            return self::$countryCache;
        }

        $territories = ResourceBundle::create('en', 'ICUDATA-region')?->get('Countries');
        $items = [];

        if ($territories instanceof ResourceBundle) {
            foreach ($territories as $code => $name) {
                if (! is_string($code)
                    || ! preg_match('/^[A-Z]{2}$/', $code)
                    || in_array($code, self::EXCLUDED_TERRITORIES, true)
                    || ! is_string($name)) {
                    continue;
                }

                $timezone = self::timezoneFor($code);
                $currency = self::currencyFor($code);

                if ($timezone === null || $currency === null) {
                    continue;
                }

                $items[] = [
                    'code' => $code,
                    'name' => $name,
                    'timezone' => $timezone,
                    'currency' => $currency,
                ];
            }
        }

        usort($items, fn (array $left, array $right): int => $left['name'] <=> $right['name']);

        return self::$countryCache = $items;
    }

    /** @return list<array{code: string, name: string}> */
    public static function currencies(): array
    {
        $names = self::currencyNames();
        $codes = array_values(array_unique(array_column(self::countries(), 'currency')));
        sort($codes);

        return array_map(fn (string $code): array => [
            'code' => $code,
            'name' => $names[$code] ?? $code,
        ], $codes);
    }

    /** @return list<string> */
    public static function timezones(): array
    {
        return DateTimeZone::listIdentifiers(DateTimeZone::ALL);
    }

    /** @return list<string> */
    public static function countryCodes(): array
    {
        return array_column(self::countries(), 'code');
    }

    /** @return list<string> */
    public static function currencyCodes(): array
    {
        return array_column(self::currencies(), 'code');
    }

    private static function timezoneFor(string $countryCode): ?string
    {
        if (isset(self::TIMEZONE_OVERRIDES[$countryCode])) {
            return self::TIMEZONE_OVERRIDES[$countryCode];
        }

        try {
            $timezones = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $countryCode);
        } catch (Throwable) {
            return null;
        }

        return $timezones[0] ?? null;
    }

    private static function currencyFor(string $countryCode): ?string
    {
        $formatter = new NumberFormatter('en_'.$countryCode, NumberFormatter::CURRENCY);
        $currency = $formatter->getTextAttribute(NumberFormatter::CURRENCY_CODE);

        return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
    }

    /** @return array<string, string> */
    private static function currencyNames(): array
    {
        $currencies = ResourceBundle::create('en', 'ICUDATA-curr')?->get('Currencies');
        $names = [];

        if (! $currencies instanceof ResourceBundle) {
            return $names;
        }

        foreach ($currencies as $code => $details) {
            if (! is_string($code) || ! $details instanceof ResourceBundle) {
                continue;
            }

            $name = $details->get(1);
            if (is_string($name)) {
                $names[$code] = $name;
            }
        }

        return $names;
    }
}
