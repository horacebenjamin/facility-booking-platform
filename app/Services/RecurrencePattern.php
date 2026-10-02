<?php

namespace App\Services;

use App\Enums\RecurrenceFrequency;
use DateTimeZone;
use InvalidArgumentException;

final readonly class RecurrencePattern
{
    public const MaximumIntervalWeeks = 255;

    public const MaximumOccurrences = 104;

    public function __construct(
        public RecurrenceFrequency $frequency,
        public int $intervalWeeks,
        public int $occurrenceCount,
        public string $timezone,
    ) {
        if ($intervalWeeks < 1 || $intervalWeeks > self::MaximumIntervalWeeks) {
            throw new InvalidArgumentException('The weekly interval must be between 1 and 255 weeks.');
        }

        if ($occurrenceCount < 2 || $occurrenceCount > self::MaximumOccurrences) {
            throw new InvalidArgumentException('A recurring series must contain between 2 and 104 occurrences.');
        }

        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('The recurrence timezone must be a valid IANA timezone.');
        }
    }
}
