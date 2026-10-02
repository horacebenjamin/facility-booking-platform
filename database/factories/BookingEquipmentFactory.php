<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingEquipment;
use App\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingEquipment>
 */
class BookingEquipmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'centre_id' => static function (array $attributes): int {
                return Booking::query()->whereKey($attributes['booking_id'])->sole()->centre_id;
            },
            'equipment_id' => static function (array $attributes): Equipment {
                return Equipment::factory()->createOne(['centre_id' => $attributes['centre_id']]);
            },
            'requested_quantity' => fake()->numberBetween(1, 10),
        ];
    }
}
