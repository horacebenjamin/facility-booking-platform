<?php

namespace App\Services;

final readonly class BookingRequestValidation
{
    public function __construct(
        public AvailabilityResult $availability,
        public ?PricingQuoteResult $pricing,
        public ?OperationalOccupancyPeriod $operationalPeriod,
        public bool $hasAllocationUnits,
    ) {}

    public function canCreate(): bool
    {
        return $this->hasAllocationUnits
            && $this->availability->isAvailable()
            && $this->pricing?->isSuccessful() === true
            && $this->operationalPeriod !== null;
    }
}
