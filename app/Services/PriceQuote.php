<?php

namespace App\Services;

final readonly class PriceQuote
{
    /**
     * @param  list<EquipmentPriceLine>  $equipmentLines
     */
    public function __construct(
        public string $currency,
        public ResourcePriceLine $resourceLine,
        public array $equipmentLines,
        public int $baseResourceAmountMinor,
        public int $equipmentAmountMinor,
        public int $subtotalMinor,
        public ?PriceDiscountLine $discount,
        public int $calculatedTotalMinor,
        public ?PriceOverrideDetails $override,
        public int $finalTotalMinor,
    ) {}
}
