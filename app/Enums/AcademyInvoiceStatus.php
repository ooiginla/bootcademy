<?php

namespace App\Enums;

enum AcademyInvoiceStatus: string
{
    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case PartiallyWrittenOff = 'partially_written_off';
    case Paid = 'paid';
    case WrittenOff = 'written_off';
    case Settled = 'settled';
    case Void = 'void';
}
