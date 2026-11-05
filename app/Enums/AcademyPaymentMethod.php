<?php

namespace App\Enums;

enum AcademyPaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Pos = 'pos';
    case Cheque = 'cheque';
    case Other = 'other';
    case Paystack = 'paystack';
}
