<?php

namespace App\Services;

use Carbon\CarbonImmutable;

final readonly class OperationalOccupancyPeriod
{
    public function __construct(
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
    ) {}
}
