<?php

namespace Database\Factories;

use App\Enums\DayOfWeek;
use App\Models\Centre;
use App\Models\CentreOperatingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CentreOperatingHour>
 */
class CentreOperatingHourFactory extends Factory
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
            'day_of_week' => DayOfWeek::Monday,
            'opens_at' => '08:00:00',
            'closes_at' => '22:00:00',
        ];
    }
}
