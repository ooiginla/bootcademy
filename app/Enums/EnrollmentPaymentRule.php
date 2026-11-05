<?php

namespace App\Enums;

enum EnrollmentPaymentRule: string
{
    case FullPayment = 'full_payment';
    case FirstInstallment = 'first_installment';

    public function label(): string
    {
        return match ($this) {
            self::FullPayment => 'After full programme payment',
            self::FirstInstallment => 'After first installment',
        };
    }
}
