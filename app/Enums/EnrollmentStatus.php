<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Withdrawn = 'withdrawn';
    case Completed = 'completed';
}
