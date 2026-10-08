<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class BookingDateTime
{
    public static function fromLocalInput(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, (string) config('booking.local_timezone'))
            ->setTimezone((string) config('app.timezone'));
    }

    public static function inLocalTimezone(CarbonInterface $dateTime): CarbonImmutable
    {
        return CarbonImmutable::instance($dateTime)
            ->setTimezone((string) config('booking.local_timezone'));
    }
}
