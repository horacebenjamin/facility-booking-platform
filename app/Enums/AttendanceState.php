<?php

namespace App\Enums;

enum AttendanceState: string
{
    case Expected = 'expected';
    case Arrived = 'arrived';
    case NoShow = 'no_show';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Expected => 'Expected',
            self::Arrived => 'Arrived',
            self::NoShow => 'No-show',
            self::Completed => 'Completed',
        };
    }
}
