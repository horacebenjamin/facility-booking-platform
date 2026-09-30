<?php

namespace Database\Factories;

use App\Models\Centre;
use App\Models\Equipment;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'centre_id' => Centre::factory(),
            'facility_id' => null,
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->paragraph(),
            'quantity' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }

    public function forFacility(Facility $facility): static
    {
        return $this->state(fn (array $attributes): array => [
            'centre_id' => $facility->centre_id,
            'facility_id' => $facility->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
