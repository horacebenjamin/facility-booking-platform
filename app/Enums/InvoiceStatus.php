<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Issued = 'issued';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Outstanding',
            self::Paid => 'Paid',
        };
    }
}
