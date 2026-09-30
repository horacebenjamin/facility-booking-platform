<?php

namespace Database\Factories;

use App\Models\AllocationOccupancy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AllocationOccupancy>
 */
class AllocationOccupancyFactory extends Factory
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
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+2 hours'),
            'expires_at' => null,
        ];
    }
}
