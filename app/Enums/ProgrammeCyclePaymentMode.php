<?php

namespace App\Enums;

enum ProgrammeCyclePaymentMode: string
{
    case OneOff = 'one_off';
    case Installments = 'installments';
}
