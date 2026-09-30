<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentAllocation>
 */
class EquipmentAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+1 month');

        return [
            'equipment_id' => Equipment::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+2 hours'),
            'expires_at' => null,
        ];
    }
}
