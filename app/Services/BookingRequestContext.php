<?php

namespace App\Services;

use App\Models\AllocationUnit;
use App\Models\Equipment;
use App\Models\Resource;
use Illuminate\Support\Collection;

final readonly class BookingRequestContext
{
    /**
     * @param  Collection<int, AllocationUnit>  $allocationUnits
     * @param  Collection<int, Equipment>  $equipmentById
     * @param  list<EquipmentRequirement>  $equipmentRequirements
     */
    public function __construct(
        public Resource $resource,
        public Collection $allocationUnits,
        public Collection $equipmentById,
        public array $equipmentRequirements,
    ) {}
}
