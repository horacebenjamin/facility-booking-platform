<?php

namespace App\Services;

use Carbon\CarbonImmutable;

final readonly class OccurrencePeriod
{
    public function __construct(
        public int $index,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
    ) {}
}
