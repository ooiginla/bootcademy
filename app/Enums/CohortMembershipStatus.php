<?php

namespace App\Enums;

enum CohortMembershipStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Transferred = 'transferred';
    case Withdrawn = 'withdrawn';
}
