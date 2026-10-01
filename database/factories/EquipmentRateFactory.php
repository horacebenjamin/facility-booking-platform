<?php

namespace Database\Factories;

use App\Enums\EquipmentChargeType;
use App\Enums\PricingRateUnit;
use App\Models\Equipment;
use App\Models\EquipmentRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentRate>
 */
class EquipmentRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'equipment_id' => Equipment::factory(),
            'charge_type' => EquipmentChargeType::SeparatelyChargeable,
            'amount_minor' => 500,
            'currency' => 'GBP',
            'rate_unit' => PricingRateUnit::Hourly,
            'effective_from' => '2026-01-01',
            'effective_until' => null,
        ];
    }

    public function included(): static
    {
        return $this->state(fn (array $attributes): array => [
            'charge_type' => EquipmentChargeType::Included,
            'amount_minor' => 0,
        ]);
    }
}
