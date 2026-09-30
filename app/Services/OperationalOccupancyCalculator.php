<?php

namespace App\Services;

use App\Models\Resource;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class OperationalOccupancyCalculator
{
    public function calculate(
        Resource $resource,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
    ): ?OperationalOccupancyPeriod {
        $startsAt = CarbonImmutable::instance($startsAt)->setTimezone(config('app.timezone'));
        $endsAt = CarbonImmutable::instance($endsAt)->setTimezone(config('app.timezone'));

        if (! $startsAt->lt($endsAt)) {
            return null;
        }

        return new OperationalOccupancyPeriod(
            startsAt: $startsAt->subMinutes($resource->setup_minutes),
            endsAt: $endsAt->addMinutes($resource->cleanup_minutes),
        );
    }
}
