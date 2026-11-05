<?php

namespace App\Enums;

enum PlayerStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Withdrawn = 'withdrawn';
    case Graduated = 'graduated';
}
