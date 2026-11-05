<?php

namespace App\Enums;

enum AcademyPaymentStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Failed = 'failed';
    case Reversed = 'reversed';
}
