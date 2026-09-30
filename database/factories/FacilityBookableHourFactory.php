<?php

namespace Database\Factories;

use App\Enums\DayOfWeek;
use App\Models\Facility;
use App\Models\FacilityBookableHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FacilityBookableHour>
 */
class FacilityBookableHourFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'day_of_week' => DayOfWeek::Monday,
            'opens_at' => '08:00:00',
            'closes_at' => '22:00:00',
        ];
    }
}
