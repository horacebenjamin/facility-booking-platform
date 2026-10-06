<?php

namespace Database\Factories;

use App\Enums\DamageFinancialFollowUp;
use App\Enums\DamageResponsibility;
use App\Enums\OperationalIssueStatus;
use App\Models\Booking;
use App\Models\DamageReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DamageReport>
 */
class DamageReportFactory extends Factory
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
            'equipment_id' => null,
            'reported_by' => User::factory(),
            'description' => fake()->paragraph(),
            'observed_at' => static function (array $attributes): mixed {
                return Booking::query()->whereKey($attributes['booking_id'])->sole()->starts_at;
            },
            'status' => OperationalIssueStatus::Open,
            'responsibility' => DamageResponsibility::Undetermined,
            'financial_follow_up' => DamageFinancialFollowUp::NotRequired,
            'follow_up_notes' => null,
        ];
    }
}
