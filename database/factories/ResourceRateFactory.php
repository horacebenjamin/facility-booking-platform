<?php

namespace Database\Factories;

use App\Enums\PricingRateUnit;
use App\Models\Resource;
use App\Models\ResourceRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceRate>
 */
class ResourceRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resource_id' => Resource::factory(),
            'amount_minor' => 2500,
            'currency' => 'GBP',
            'rate_unit' => PricingRateUnit::Hourly,
            'effective_from' => '2026-01-01',
            'effective_until' => null,
        ];
    }
}
