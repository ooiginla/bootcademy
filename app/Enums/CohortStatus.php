<?php

namespace App\Enums;

enum CohortStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
