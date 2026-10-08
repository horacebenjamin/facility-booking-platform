<?php

namespace App\Services;

use App\Models\Resource;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class AvailabilityBlockEvaluator
{
    public function __construct(private AvailabilityBlockScopeMatcher $scopeMatcher) {}

    public function isBlocked(
        Resource $resource,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        bool $lockReservations = false,
    ): bool {
        $requestedPeriod = $this->normaliseRequestedPeriod($startsAt, $endsAt);

        if ($requestedPeriod === null) {
            return false;
        }

        $query = $this->scopeMatcher->blocksFor($resource, $lockReservations)
            ->where('starts_at', '<', $requestedPeriod['endsAt'])
            ->where('ends_at', '>', $requestedPeriod['startsAt'])
            ->where(function (Builder $query) use ($requestedPeriod): void {
                $query->whereNull('ended_at')
                    ->orWhere(function (Builder $query) use ($requestedPeriod): void {
                        $query->whereColumn('ended_at', '>', 'starts_at')
                            ->where('ended_at', '>', $requestedPeriod['startsAt']);
                    });
            });

        return $lockReservations
            ? $query->orderBy('id')->lockForUpdate()->first() !== null
            : $query->exists();
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
