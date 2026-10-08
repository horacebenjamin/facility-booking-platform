<?php

namespace App\Services;

use App\Models\Resource;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class AllocationConflictEvaluator
{
    public function hasConflict(
        Resource $resource,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?CarbonInterface $evaluatedAt = null,
        ?int $excludedBookingId = null,
        bool $lockReservations = false,
    ): bool {
        $requestedPeriod = $this->normaliseRequestedPeriod($startsAt, $endsAt);

        if ($requestedPeriod === null) {
            return false;
        }

        $evaluatedAt = CarbonImmutable::instance($evaluatedAt ?? now())
            ->setTimezone(config('app.timezone'));

        if ($lockReservations) {
            $query = DB::table('allocation_occupancies as occupancy')
                ->join('allocation_occupancy_allocation_unit as protection', 'protection.allocation_occupancy_id', '=', 'occupancy.id')
                ->join('allocation_unit_resource as mapping', 'mapping.allocation_unit_id', '=', 'protection.allocation_unit_id')
                ->where('mapping.resource_id', $resource->id)
                ->where('occupancy.starts_at', '<', $requestedPeriod['endsAt'])
                ->where('occupancy.ends_at', '>', $requestedPeriod['startsAt'])
                ->where(fn (QueryBuilder $query): QueryBuilder => $query->whereNull('occupancy.expires_at')->orWhere('occupancy.expires_at', '>', $evaluatedAt));
            if ($excludedBookingId !== null) {
                $query->where(fn (QueryBuilder $query): QueryBuilder => $query->whereNull('occupancy.booking_id')->orWhere('occupancy.booking_id', '!=', $excludedBookingId));
            }

            return $query->orderBy('occupancy.id')->lockForUpdate()->first(['occupancy.id']) !== null;
        }

        return $resource->allocationUnits()
            ->whereHas('occupancies', function (Builder $query) use ($requestedPeriod, $evaluatedAt, $excludedBookingId): void {
                $query
                    ->where('starts_at', '<', $requestedPeriod['endsAt'])
                    ->where('ends_at', '>', $requestedPeriod['startsAt'])
                    ->where(function (Builder $query) use ($evaluatedAt): void {
                        $query
                            ->whereNull('expires_at')
                            ->orWhere('expires_at', '>', $evaluatedAt);
                    });

                if ($excludedBookingId !== null) {
                    $query->where(function (Builder $query) use ($excludedBookingId): void {
                        $query
                            ->whereNull('booking_id')
                            ->orWhere('booking_id', '!=', $excludedBookingId);
                    });
                }
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
