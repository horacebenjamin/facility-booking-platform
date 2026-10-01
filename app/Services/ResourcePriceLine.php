<?php

namespace App\Services;

use App\Enums\PricingRateUnit;
use Carbon\CarbonImmutable;

final readonly class ResourcePriceLine
{
    public function __construct(
        public string $resourceName,
        public int $hourlyRateMinor,
        public string $currency,
        public PricingRateUnit $rateUnit,
        public CarbonImmutable $effectiveFrom,
        public ?CarbonImmutable $effectiveUntil,
        public int $durationSeconds,
        public int $amountMinor,
    ) {}
}
