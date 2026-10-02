<?php

namespace App\Enums;

enum FinancialStatus: string
{
    case NotDue = 'not_due';

    public function label(): string
    {
        return match ($this) {
            self::NotDue => 'Not due',
        };
    }
}
