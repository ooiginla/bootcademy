<?php

namespace Tests\Unit;

use App\Support\CalendarDays;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class CalendarDaysTest extends TestCase
{
    public function test_display_differences_round_away_from_zero(): void
    {
        $from = CarbonImmutable::parse('2026-11-07 00:00:00', 'Africa/Lagos');

        $this->assertSame(55, CalendarDays::roundedUntil($from, CarbonImmutable::parse('2026-12-31 00:00:00', 'UTC')));
        $this->assertSame(-7, CalendarDays::roundedUntil($from, CarbonImmutable::parse('2026-10-31 00:00:00', 'UTC')));
    }

    public function test_automation_uses_calendar_days_in_the_academy_timezone(): void
    {
        $from = CarbonImmutable::parse('2026-11-07 00:00:00', 'Africa/Lagos');

        $this->assertSame(54, CalendarDays::until($from, CarbonImmutable::parse('2026-12-31 00:00:00', 'UTC')));
        $this->assertSame(-7, CalendarDays::until($from, CarbonImmutable::parse('2026-10-31 00:00:00', 'UTC')));
    }
}
