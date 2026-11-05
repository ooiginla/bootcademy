<?php

namespace Tests\Unit;

use App\Support\AccessibleColor;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AccessibleColorTest extends TestCase
{
    #[DataProvider('foregrounds')]
    public function test_it_selects_the_more_readable_foreground(string $background, string $expected): void
    {
        $this->assertSame($expected, AccessibleColor::foreground($background));
    }

    /** @return array<string, array{string, string}> */
    public static function foregrounds(): array
    {
        return [
            'black background' => ['#000000', '#FFFFFF'],
            'white background' => ['#FFFFFF', '#111827'],
            'bright yellow background' => ['#FFFF00', '#111827'],
            'deep blue background' => ['#172554', '#FFFFFF'],
        ];
    }

    public function test_it_normalizes_valid_colours(): void
    {
        $this->assertSame('#A1B2C3', AccessibleColor::normalize(' #a1b2c3 '));
    }

    public function test_it_rejects_invalid_colours(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AccessibleColor::normalize('blue');
    }
}
