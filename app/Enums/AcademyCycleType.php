<?php

namespace App\Enums;

enum AcademyCycleType: string
{
    case Fixed = 'fixed';
    case Rolling = 'rolling';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed dates',
            self::Rolling => 'Rolling duration',
        };
    }
}
