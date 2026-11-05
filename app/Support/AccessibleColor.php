<?php

namespace App\Support;

use InvalidArgumentException;

class AccessibleColor
{
    public static function normalize(string $color): string
    {
        $color = strtoupper(trim($color));

        if (! preg_match('/^#[0-9A-F]{6}$/', $color)) {
            throw new InvalidArgumentException('Colour must use the #RRGGBB format.');
        }

        return $color;
    }

    public static function foreground(string $background): string
    {
        $background = self::normalize($background);
        $red = intval(hexdec(substr($background, 1, 2)));
        $green = intval(hexdec(substr($background, 3, 2)));
        $blue = intval(hexdec(substr($background, 5, 2)));
        $luminance = self::luminance($red, $green, $blue);
        $whiteContrast = 1.05 / ($luminance + 0.05);
        $blackContrast = ($luminance + 0.05) / 0.05;

        return $whiteContrast >= $blackContrast ? '#FFFFFF' : '#111827';
    }

    /** @return array{primary: string, primary_foreground: string, secondary: string, secondary_foreground: string} */
    public static function theme(?string $primary, ?string $secondary): array
    {
        $primary = self::normalize($primary ?: config('branding.default_primary_color'));
        $secondary = self::normalize($secondary ?: config('branding.default_secondary_color'));

        return [
            'primary' => $primary,
            'primary_foreground' => self::foreground($primary),
            'secondary' => $secondary,
            'secondary_foreground' => self::foreground($secondary),
        ];
    }

    private static function luminance(int $red, int $green, int $blue): float
    {
        $channels = array_map(static function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.04045
                ? $value / 12.92
                : (($value + 0.055) / 1.055) ** 2.4;
        }, [$red, $green, $blue]);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
