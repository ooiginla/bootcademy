<?php

namespace App\Enums;

enum ReviewAudience: string
{
    case Guardians = 'guardians';
    case Players = 'players';

    public function label(): string
    {
        return match ($this) {
            self::Guardians => 'Parents / guardians',
            self::Players => 'Students / players',
        };
    }
}
