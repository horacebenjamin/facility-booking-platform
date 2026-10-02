<?php

namespace Database\Factories;

use App\Enums\RecurrenceFrequency;
use App\Models\BookingSeries;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookingSeries>
 */
class BookingSeriesFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+1 month');

        return [
            'identifier' => (string) Str::uuid(),
            'customer_id' => User::factory(),
            'resource_id' => Resource::factory(),
            'facility_id' => static function (array $attributes): int {
                return Resource::query()->whereKey($attributes['resource_id'])->sole()->facility_id;
            },
            'centre_id' => static function (array $attributes): int {
                return Facility::query()->whereKey($attributes['facility_id'])->sole()->centre_id;
            },
            'recurrence_frequency' => RecurrenceFrequency::Weekly,
            'interval_weeks' => 1,
            'occurrence_count' => 4,
            'timezone' => 'Europe/London',
            'first_starts_at' => $startsAt,
            'first_ends_at' => (clone $startsAt)->modify('+2 hours'),
        ];
    }
}
