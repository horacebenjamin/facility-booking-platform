<?php

namespace App\Services;

final readonly class PricingQuoteResult
{
    private function __construct(
        public ?PriceQuote $quote,
        public ?PricingFailure $failure,
    ) {}

    public static function success(PriceQuote $quote): self
    {
        return new self($quote, null);
    }

    public static function failure(PricingFailure $failure): self
    {
        return new self(null, $failure);
    }

    public function isSuccessful(): bool
    {
        return $this->quote !== null;
    }
}
