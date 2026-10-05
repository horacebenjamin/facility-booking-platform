<?php

namespace App\Services;

use App\Models\AvailabilityBlock;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class AvailabilityBlockScopeMatcher
{
    /** @return Builder<resource> */
    public function resourcesFor(AvailabilityBlock $block): Builder
    {
        if ($block->resource_id !== null) {
            return $this->physicallyConflictingResources(Resource::query()->findOrFail($block->resource_id));
        }

        if ($block->facility_id !== null) {
            return Resource::query()->where('facility_id', $block->facility_id);
        }

        return Resource::query()->whereHas('facility', fn (Builder $query) => $query->where('centre_id', $block->centre_id));
    }

    /** @return Builder<AvailabilityBlock> */
    public function blocksFor(Resource $resource): Builder
    {
        return AvailabilityBlock::query()->where(function (Builder $query) use ($resource): void {
            $query->whereIn('resource_id', $this->physicallyConflictingResources($resource)->select('resources.id'))
                ->orWhere('facility_id', $resource->facility_id)
                ->orWhere('centre_id', function (QueryBuilder $query) use ($resource): void {
                    $query->select('centre_id')->from('facilities')->where('id', $resource->facility_id);
                });
        });
    }

    /** @return Builder<resource> */
    private function physicallyConflictingResources(Resource $resource): Builder
    {
        return Resource::query()->where('facility_id', $resource->facility_id)
            ->where(function (Builder $query) use ($resource): void {
                $query->whereKey($resource->id)
                    ->orWhereHas('allocationUnits', function (Builder $query) use ($resource): void {
                        $query->whereIn('allocation_units.id', $resource->allocationUnits()->select('allocation_units.id'));
                    });
            });
    }
}
