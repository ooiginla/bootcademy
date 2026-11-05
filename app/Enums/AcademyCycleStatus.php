<?php

namespace App\Enums;

enum AcademyCycleStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';
}
