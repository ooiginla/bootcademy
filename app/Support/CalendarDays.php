<?php

namespace App\Support;

use Carbon\CarbonInterface;

class CalendarDays
{
    public static function roundedUntil(CarbonInterface $from, CarbonInterface $to): int
    {
        $difference = $from->diffInDays($to, false);

        return (int) ($difference < 0 ? floor($difference) : ceil($difference));
    }

    public static function until(CarbonInterface $from, CarbonInterface $to): int
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->setTimezone($start->getTimezone())->startOfDay();
        $difference = $start->diffInDays($end, false);

        return (int) ($difference < 0 ? floor($difference) : ceil($difference));
    }
}
