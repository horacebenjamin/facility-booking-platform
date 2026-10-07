<?php

namespace App\Services\Reporting;

use Carbon\CarbonImmutable;

final readonly class ReportFilters
{
    public const string TIMEZONE = 'Europe/London';

    public const int MAX_RANGE_DAYS = 366;

    public function __construct(
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public ?int $centreId = null,
        public ?int $facilityId = null,
        public ?int $resourceId = null,
        public ?int $customerId = null,
    ) {}

    public static function forLocalDates(
        string $startDate,
        string $endDate,
        ?int $centreId = null,
        ?int $facilityId = null,
        ?int $resourceId = null,
        ?int $customerId = null,
    ): self {
        return new self(
            CarbonImmutable::parse($startDate, self::TIMEZONE)->startOfDay()->utc(),
            CarbonImmutable::parse($endDate, self::TIMEZONE)->addDay()->startOfDay()->utc(),
            $centreId,
            $facilityId,
            $resourceId,
            $customerId,
        );
    }

    /**
     * Validation rules for the local start/end date inputs used to build report filters.
     *
     * @return array<string, list<string>>
     */
    public static function dateRules(): array
    {
        return [
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
        ];
    }

    /** The inclusive local calendar date on which the reporting period ends. */
    public function localEndDate(): string
    {
        return $this->endsAt->setTimezone(self::TIMEZONE)->subDay()->toDateString();
    }
}
