<?php

namespace Database\Factories;

use App\Enums\DayOfWeek;
use App\Models\Resource;
use App\Models\ResourceBookableHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceBookableHour>
 */
class ResourceBookableHourFactory extends Factory
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
            'day_of_week' => DayOfWeek::Monday,
            'opens_at' => '08:00:00',
            'closes_at' => '22:00:00',
        ];
    }
}
