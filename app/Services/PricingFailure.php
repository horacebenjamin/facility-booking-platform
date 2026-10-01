<?php

namespace App\Services;

use App\Enums\PricingFailureReason;

final readonly class PricingFailure
{
    public function __construct(
        public PricingFailureReason $reason,
        public ?string $subjectName = null,
    ) {}
}
