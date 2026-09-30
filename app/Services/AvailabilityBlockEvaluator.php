<?php

namespace App\Services;

use App\Models\AvailabilityBlock;
use App\Models\Resource;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class AvailabilityBlockEvaluator
{
    public function isBlocked(
        Resource $resource,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
    ): bool {
        $requestedPeriod = $this->normaliseRequestedPeriod($startsAt, $endsAt);

        if ($requestedPeriod === null) {
            return false;
        }

        return AvailabilityBlock::query()
            ->where('starts_at', '<', $requestedPeriod['endsAt'])
            ->where('ends_at', '>', $requestedPeriod['startsAt'])
            ->where(function (Builder $query) use ($resource): void {
                $query
                    ->where('resource_id', $resource->id)
                    ->orWhere('facility_id', $resource->facility_id)
                    ->orWhere('centre_id', function (QueryBuilder $query) use ($resource): void {
                        $query
                            ->select('centre_id')
                            ->from('facilities')
                            ->where('id', $resource->facility_id);
                    });
            })
            ->exists();
    }

    /**
     * @return array{startsAt: CarbonImmutable, endsAt: CarbonImmutable}|null
     */
    private function normaliseRequestedPeriod(CarbonInterface $startsAt, CarbonInterface $endsAt): ?array
    {
        $startsAt = CarbonImmutable::instance($startsAt)->setTimezone(config('app.timezone'));
        $endsAt = CarbonImmutable::instance($endsAt)->setTimezone(config('app.timezone'));

        if (! $startsAt->lt($endsAt)) {
            return null;
        }

        return [
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
        ];
    }
}
