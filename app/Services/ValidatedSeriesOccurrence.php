<?php

namespace App\Services;

final readonly class ValidatedSeriesOccurrence
{
    public function __construct(
        public OccurrencePeriod $period,
        public PriceQuote $priceQuote,
        public OperationalOccupancyPeriod $operationalPeriod,
        public BookingRequestValidation $bookingValidation,
    ) {}
}
