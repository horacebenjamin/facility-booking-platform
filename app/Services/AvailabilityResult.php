<?php

namespace App\Services;

use App\Enums\AvailabilityReason;

final readonly class AvailabilityResult
{
    /**
     * @var list<AvailabilityReason>
     */
    private array $reasons;

    public function __construct(AvailabilityReason ...$reasons)
    {
        $uniqueReasons = [];

        foreach ($reasons as $reason) {
            $uniqueReasons[$reason->value] = $reason;
        }

        $this->reasons = array_values($uniqueReasons);
    }

    public function isAvailable(): bool
    {
        return $this->reasons === [];
    }

    /**
     * @return list<AvailabilityReason>
     */
    public function reasons(): array
    {
        return $this->reasons;
    }

    public function hasReason(AvailabilityReason $reason): bool
    {
        return in_array($reason, $this->reasons, true);
    }
}
