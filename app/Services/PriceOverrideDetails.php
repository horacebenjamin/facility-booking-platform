<?php

namespace App\Services;

use Carbon\CarbonImmutable;

final readonly class PriceOverrideDetails
{
    public function __construct(
        public int $originalTotalMinor,
        public int $adjustedTotalMinor,
        public string $reason,
        public int $responsibleUserId,
        public string $responsibleUserName,
        public CarbonImmutable $adjustedAt,
    ) {}
}
