<?php

namespace Database\Factories;

use App\Enums\OperationalIssueStatus;
use App\Models\Booking;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'centre_id' => static function (array $attributes): int {
                return Booking::query()->whereKey($attributes['booking_id'])->sole()->centre_id;
            },
            'booking_id' => Booking::factory(),
            'resource_id' => static function (array $attributes): int {
                return Booking::query()->whereKey($attributes['booking_id'])->sole()->resource_id;
            },
            'reported_by' => User::factory(),
            'issue_type' => 'health_and_safety',
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'immediate_action' => null,
            'occurred_at' => static function (array $attributes): mixed {
                return Booking::query()->whereKey($attributes['booking_id'])->sole()->starts_at;
            },
            'status' => OperationalIssueStatus::Open,
            'follow_up_notes' => null,
        ];
    }
}
