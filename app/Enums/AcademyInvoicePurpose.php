<?php

namespace App\Enums;

enum AcademyInvoicePurpose: string
{
    case Application = 'application';
    case Programme = 'programme';
    case Custom = 'custom';
}
