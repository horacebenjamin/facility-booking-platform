<?php

namespace Database\Factories;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganisationMembership>
 */
class OrganisationMembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'user_id' => User::factory(),
            'role' => OrganisationRole::Member,
            'joined_at' => now(),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (): array => ['role' => OrganisationRole::Owner]);
    }
}
