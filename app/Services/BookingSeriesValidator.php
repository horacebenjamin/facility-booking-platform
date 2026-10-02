<?php

namespace App\Services;

use App\Enums\RecurringBookingConflictReason;
use Carbon\CarbonImmutable;

class BookingSeriesValidator
{
    public function __construct(private BookingRequestEngine $bookingRequestEngine) {}

    /**
     * @param  list<OccurrencePeriod>  $periods
     */
    public function validate(
        BookingRequestContext $context,
        array $periods,
        CarbonImmutable $evaluatedAt,
    ): BookingSeriesValidationResult {
        $validOccurrences = [];
        $conflicts = [];
        $latestAcceptedOperationalEnd = null;

        foreach ($periods as $period) {
            $validation = $this->bookingRequestEngine->validate(
                $context,
                $period->startsAt,
                $period->endsAt,
                $evaluatedAt,
            );
            $reasons = [];

            if (! $validation->hasAllocationUnits) {
                $reasons[] = RecurringBookingConflictReason::ResourceAllocationUnavailable;
            }

            if (! $validation->availability->isAvailable()) {
                $reasons[] = RecurringBookingConflictReason::Availability;
            }

            if ($validation->pricing !== null && ! $validation->pricing->isSuccessful()) {
                $reasons[] = RecurringBookingConflictReason::Pricing;
            }

            if ($validation->operationalPeriod !== null
                && $latestAcceptedOperationalEnd !== null
                && $validation->operationalPeriod->startsAt->lt($latestAcceptedOperationalEnd)) {
                $reasons[] = RecurringBookingConflictReason::SeriesOverlap;
            }

            if ($reasons !== []) {
                $conflicts[] = new SeriesOccurrenceConflict(
                    period: $period,
                    reasons: $reasons,
                    availabilityReasons: $validation->availability->reasons(),
                    pricingFailure: $validation->pricing?->failure,
                );

                continue;
            }

            $priceQuote = $validation->pricing?->quote;
            $operationalPeriod = $validation->operationalPeriod;

            if ($priceQuote === null || $operationalPeriod === null) {
                $conflicts[] = new SeriesOccurrenceConflict(
                    period: $period,
                    reasons: [RecurringBookingConflictReason::Pricing],
                    availabilityReasons: $validation->availability->reasons(),
                    pricingFailure: $validation->pricing?->failure,
                );

                continue;
            }

            $validOccurrences[] = new ValidatedSeriesOccurrence(
                period: $period,
                priceQuote: $priceQuote,
                operationalPeriod: $operationalPeriod,
                bookingValidation: $validation,
            );
            $latestAcceptedOperationalEnd = $operationalPeriod->endsAt;
        }

        return new BookingSeriesValidationResult($validOccurrences, $conflicts);
    }
}
