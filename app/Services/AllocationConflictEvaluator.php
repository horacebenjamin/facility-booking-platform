<?php

namespace App\Services;

use App\Models\Resource;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class AllocationConflictEvaluator
{
    public function hasConflict(
        Resource $resource,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?CarbonInterface $evaluatedAt = null,
    ): bool {
        $requestedPeriod = $this->normaliseRequestedPeriod($startsAt, $endsAt);

        if ($requestedPeriod === null) {
            return false;
        }

        $evaluatedAt = CarbonImmutable::instance($evaluatedAt ?? now())
            ->setTimezone(config('app.timezone'));

        return $resource->allocationUnits()
            ->whereHas('occupancies', function (Builder $query) use ($requestedPeriod, $evaluatedAt): void {
                $query
                    ->where('starts_at', '<', $requestedPeriod['endsAt'])
                    ->where('ends_at', '>', $requestedPeriod['startsAt'])
                    ->where(function (Builder $query) use ($evaluatedAt): void {
                        $query
                            ->whereNull('expires_at')
                            ->orWhere('expires_at', '>', $evaluatedAt);
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
