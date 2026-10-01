<?php

namespace App\Services;

final readonly class PricingDiscount
{
    public function __construct(
        public int $amountMinor,
        public string $description,
    ) {}
}
