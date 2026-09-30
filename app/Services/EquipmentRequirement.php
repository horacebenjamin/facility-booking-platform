<?php

namespace App\Services;

use App\Models\Equipment;

final readonly class EquipmentRequirement
{
    public function __construct(
        public Equipment $equipment,
        public int $quantity,
    ) {}
}
