<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Requested = 'requested';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested / Awaiting Management Approval',
        };
    }
}
