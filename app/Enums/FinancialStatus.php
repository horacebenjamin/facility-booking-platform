<?php

namespace App\Enums;

enum FinancialStatus: string
{
    case NotDue = 'not_due';
    case AwaitingPayment = 'awaiting_payment';

    public function label(): string
    {
        return match ($this) {
            self::NotDue => 'Not due',
            self::AwaitingPayment => 'Awaiting payment',
        };
    }
}
