<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingSeries;

final readonly class CreateBookingSeriesResult
{
    /**
     * @param  list<Booking>  $bookings
     */
    public function __construct(
        public BookingSeriesValidationResult $validation,
        public ?BookingSeries $series = null,
        public array $bookings = [],
    ) {}

    public function wasCreated(): bool
    {
        return $this->series !== null;
    }
}
