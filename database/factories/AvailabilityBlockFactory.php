<?php

namespace Database\Factories;

use App\Enums\AvailabilityBlockType;
use App\Models\AvailabilityBlock;
use App\Models\Centre;
use App\Models\Facility;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityBlock>
 */
class AvailabilityBlockFactory extends Factory
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
            'centre_id' => Centre::factory(),
            'facility_id' => null,
            'resource_id' => null,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+2 hours'),
            'reason' => fake()->optional()->sentence(),
            'type' => AvailabilityBlockType::Other,
        ];
    }

    public function forCentre(Centre $centre): static
    {
        return $this->state(fn (): array => [
            'centre_id' => $centre->id,
            'facility_id' => null,
            'resource_id' => null,
        ]);
    }

    public function forFacility(Facility $facility): static
    {
        return $this->state(fn (): array => [
            'centre_id' => null,
            'facility_id' => $facility->id,
            'resource_id' => null,
        ]);
    }

    public function forResource(Resource $resource): static
    {
        return $this->state(fn (): array => [
            'centre_id' => null,
            'facility_id' => null,
            'resource_id' => $resource->id,
        ]);
    }
}
