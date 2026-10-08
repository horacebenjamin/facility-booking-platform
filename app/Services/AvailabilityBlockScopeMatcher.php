<?php

namespace App\Services;

use App\Models\AvailabilityBlock;
use App\Models\Facility;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class AvailabilityBlockScopeMatcher
{
    /** @return Builder<resource> */
    public function resourcesFor(AvailabilityBlock $block, bool $lockReservations = false): Builder
    {
        if ($block->resource_id !== null) {
            $resourceQuery = Resource::query();
            if ($lockReservations) {
                $resourceQuery->lockForUpdate();
            }

            return $this->physicallyConflictingResources($resourceQuery->findOrFail($block->resource_id), $lockReservations);
        }

        if ($block->facility_id !== null) {
            return Resource::query()->where('facility_id', $block->facility_id);
        }

        if ($lockReservations) {
            $facilityIds = Facility::query()->where('centre_id', $block->centre_id)->orderBy('id')->lockForUpdate()->pluck('id')->all();

            return Resource::query()->whereIn('facility_id', $facilityIds);
        }

        return Resource::query()->whereHas('facility', fn (Builder $query) => $query->where('centre_id', $block->centre_id));
    }

    /** @return Builder<AvailabilityBlock> */
    public function blocksFor(Resource $resource, bool $lockReservations = false): Builder
    {
        if ($lockReservations) {
            $facility = Facility::query()->lockForUpdate()->findOrFail($resource->facility_id);
            $resourceIds = $this->physicallyConflictingResources($resource, true)->orderBy('id')->lockForUpdate()->pluck('id')->all();

            return AvailabilityBlock::query()->where(function (Builder $query) use ($resource, $facility, $resourceIds): void {
                $query->whereIn('resource_id', $resourceIds)
                    ->orWhere('facility_id', $resource->facility_id)
                    ->orWhere('centre_id', $facility->centre_id);
            });
        }

        return AvailabilityBlock::query()->where(function (Builder $query) use ($resource, $lockReservations): void {
            $query->whereIn('resource_id', $this->physicallyConflictingResources($resource, $lockReservations)->select('resources.id'))
                ->orWhere('facility_id', $resource->facility_id)
                ->orWhere('centre_id', function (QueryBuilder $query) use ($resource): void {
                    $query->select('centre_id')->from('facilities')->where('id', $resource->facility_id);
                });
        });
    }

    /** @return Builder<resource> */
    private function physicallyConflictingResources(Resource $resource, bool $lockReservations = false): Builder
    {
        if ($lockReservations) {
            $unitIds = $resource->allocationUnits()->orderBy('allocation_units.id')->lockForUpdate()->pluck('allocation_units.id');
            $resourceIds = DB::table('allocation_unit_resource')->whereIn('allocation_unit_id', $unitIds)
                ->orderBy('resource_id')->lockForUpdate()->pluck('resource_id')->all();

            return Resource::query()->where('facility_id', $resource->facility_id)->whereKey(array_unique([$resource->id, ...$resourceIds]));
        }

        return Resource::query()->where('facility_id', $resource->facility_id)
            ->where(function (Builder $query) use ($resource): void {
                $query->whereKey($resource->id)
                    ->orWhereHas('allocationUnits', function (Builder $query) use ($resource): void {
                        $query->whereIn('allocation_units.id', $resource->allocationUnits()->select('allocation_units.id'));
                    });
            });
    }
}
