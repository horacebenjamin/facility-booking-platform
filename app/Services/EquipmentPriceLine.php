<?php

namespace App\Services;

use App\Enums\EquipmentChargeType;
use App\Enums\PricingRateUnit;
use Carbon\CarbonImmutable;

final readonly class EquipmentPriceLine
{
    public function __construct(
        public string $equipmentName,
        public int $quantity,
        public EquipmentChargeType $chargeType,
        public int $hourlyRateMinor,
        public string $currency,
        public PricingRateUnit $rateUnit,
        public CarbonImmutable $effectiveFrom,
        public ?CarbonImmutable $effectiveUntil,
        public int $durationSeconds,
        public int $amountMinor,
    ) {}
}
