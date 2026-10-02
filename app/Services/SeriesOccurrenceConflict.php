<?php

namespace App\Services;

use App\Enums\AvailabilityReason;
use App\Enums\RecurringBookingConflictReason;

final readonly class SeriesOccurrenceConflict
{
    /**
     * @param  list<RecurringBookingConflictReason>  $reasons
     * @param  list<AvailabilityReason>  $availabilityReasons
     */
    public function __construct(
        public OccurrencePeriod $period,
        public array $reasons,
        public array $availabilityReasons = [],
        public ?PricingFailure $pricingFailure = null,
    ) {}
}
