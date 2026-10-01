<?php

namespace App\Services;

final readonly class PricingContext
{
    public function __construct(
        public ?string $customerClassification = null,
        public ?PricingDiscount $discount = null,
        public ?PricingOverride $override = null,
    ) {}
}
