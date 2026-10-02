<?php

namespace App\Services;

final readonly class BookingSeriesValidationResult
{
    /**
     * @param  list<ValidatedSeriesOccurrence>  $validOccurrences
     * @param  list<SeriesOccurrenceConflict>  $conflicts
     */
    public function __construct(
        public array $validOccurrences,
        public array $conflicts,
    ) {}

    public function isValid(): bool
    {
        return $this->conflicts === [];
    }
}
