<?php

namespace App\Services;

final readonly class PriceDiscountLine
{
    public function __construct(
        public int $requestedAmountMinor,
        public int $amountMinor,
        public string $description,
    ) {}
}
