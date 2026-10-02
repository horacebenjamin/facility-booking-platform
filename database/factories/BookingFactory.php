<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\FinancialStatus;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
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
            'reference' => 'BKG-'.fake()->unique()->numerify('########'),
            'customer_id' => User::factory(),
            'resource_id' => Resource::factory(),
            'facility_id' => static function (array $attributes): int {
                return Resource::query()->whereKey($attributes['resource_id'])->sole()->facility_id;
            },
            'centre_id' => static function (array $attributes): int {
                return Facility::query()->whereKey($attributes['facility_id'])->sole()->centre_id;
            },
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+2 hours'),
            'status' => BookingStatus::Requested,
            'financial_status' => FinancialStatus::NotDue,
        ];
    }
}
