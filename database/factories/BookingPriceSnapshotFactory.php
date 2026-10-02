<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingPriceSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingPriceSnapshot>
 */
class BookingPriceSnapshotFactory extends Factory
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
            'currency' => 'GBP',
            'resource_amount_minor' => 5000,
            'equipment_amount_minor' => 0,
            'subtotal_minor' => 5000,
            'discount_requested_amount_minor' => null,
            'discount_amount_minor' => 0,
            'discount_description' => null,
            'calculated_total_minor' => 5000,
            'final_total_minor' => 5000,
            'override_original_total_minor' => null,
            'override_adjusted_total_minor' => null,
            'override_reason' => null,
            'override_responsible_user_id' => null,
            'override_responsible_user_name' => null,
            'override_adjusted_at' => null,
        ];
    }
}
