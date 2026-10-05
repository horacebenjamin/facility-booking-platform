<?php

namespace Database\Factories;

use App\Enums\ClosureImpactStatus;
use App\Models\AvailabilityBlock;
use App\Models\AvailabilityBlockBookingImpact;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AvailabilityBlockBookingImpact> */
class AvailabilityBlockBookingImpactFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['availability_block_id' => AvailabilityBlock::factory(), 'booking_id' => Booking::factory(), 'detected_at' => now(), 'status' => ClosureImpactStatus::Unresolved, 'resolved_at' => null, 'resolved_by' => null];
    }
}
