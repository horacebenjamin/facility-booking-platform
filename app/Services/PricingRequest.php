<?php

namespace App\Services;

use App\Models\Resource;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final readonly class PricingRequest
{
    public CarbonImmutable $startsAt;

    public CarbonImmutable $endsAt;

    /**
     * @param  list<EquipmentRequirement>  $equipmentRequirements
     */
    public function __construct(
        public Resource $resource,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        public array $equipmentRequirements = [],
        public ?PricingContext $context = null,
    ) {
        $this->startsAt = CarbonImmutable::instance($startsAt)->setTimezone(config('app.timezone'));
        $this->endsAt = CarbonImmutable::instance($endsAt)->setTimezone(config('app.timezone'));
    }
}
