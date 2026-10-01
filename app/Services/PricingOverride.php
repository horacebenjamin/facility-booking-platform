<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final readonly class PricingOverride
{
    public CarbonImmutable $adjustedAt;

    public function __construct(
        public User $actor,
        public int $adjustedTotalMinor,
        public string $reason,
        CarbonInterface $adjustedAt,
    ) {
        $this->adjustedAt = CarbonImmutable::instance($adjustedAt)->setTimezone(config('app.timezone'));
    }
}
