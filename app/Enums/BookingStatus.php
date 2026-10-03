<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested / Awaiting Management Approval',
            self::Approved => 'Approved',
            self::Confirmed => 'Confirmed',
            self::Rejected => 'Rejected',
        };
    }
}
