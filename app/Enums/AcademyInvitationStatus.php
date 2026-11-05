<?php

namespace App\Enums;

enum AcademyInvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Revoked = 'revoked';
}
