<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Models\AllocationOccupancy;
use App\Models\Centre;
use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use App\Models\EquipmentRate;
use App\Models\Facility;
use App\Models\FacilityBookableHour;
use App\Models\Resource;
use App\Models\ResourceBookableHour;
use App\Models\ResourceRate;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PricingAcceptanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_an_available_public_selection_returns_a_deterministic_explainable_quote_without_writing_state(): void
    {
        $resource = $this->bookableResource('Sports Hall');
        ResourceRate::factory()->for($resource)->create(['amount_minor' => 7200]);
        $chargeableEquipment = Equipment::factory()->for($resource->facility->centre)->create([
            'name' => 'Portable PA system',
            'quantity' => 1,
        ]);
        EquipmentRate::factory()->for($chargeableEquipment)->create(['amount_minor' => 1500]);
        $includedEquipment = Equipment::factory()->for($resource->facility->centre)->create([
            'name' => 'Badminton nets',
            'quantity' => 2,
        ]);
        EquipmentRate::factory()->for($includedEquipment)->included()->create();
        $counts = [
            'activity_log' => 0,
            'allocation_occupancies' => AllocationOccupancy::count(),
            'equipment_allocations' => EquipmentAllocation::count(),
        ];
        $payload = $this->payload($resource, [
            ['equipment_id' => $chargeableEquipment->id, 'quantity' => 1],
            ['equipment_id' => $includedEquipment->id, 'quantity' => 2],
        ]);

        $this->postJson(route('availability.check'), $payload)
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'available' => true,
                    'reasons' => [],
                ],
            ]);

        $quote = $this->postJson(route('pricing.quote'), $payload);

        $quote->assertOk()
            ->assertJsonPath('data.currency', 'GBP')
            ->assertJsonPath('data.duration_seconds', 5400)
            ->assertJsonPath('data.resource.amount_minor', 10800)
            ->assertJsonPath('data.equipment.0.amount_minor', 2250)
            ->assertJsonPath('data.equipment.1.charge_type', 'included')
            ->assertJsonPath('data.equipment.1.amount_minor', 0)
            ->assertJsonPath('data.subtotal_minor', 13050)
            ->assertJsonPath('data.final_total_minor', 13050);

        foreach ($counts as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
    }

    public function test_different_resources_at_different_centres_return_their_configured_prices(): void
    {
        $firstResource = $this->bookableResource('Sports Hall');
        $secondResource = $this->bookableResource('Astro Turf');
        ResourceRate::factory()->for($firstResource)->create(['amount_minor' => 3600]);
        ResourceRate::factory()->for($secondResource)->create(['amount_minor' => 5800]);

        $firstQuote = $this->postJson(route('pricing.quote'), $this->payload($firstResource));
        $secondQuote = $this->postJson(route('pricing.quote'), $this->payload($secondResource));

        $firstQuote->assertOk()->assertJsonPath('data.final_total_minor', 5400);
        $secondQuote->assertOk()->assertJsonPath('data.final_total_minor', 8700);
        $this->assertNotSame(
            $firstResource->facility->centre_id,
            $secondResource->facility->centre_id,
        );
    }

    public function test_an_unavailable_selection_does_not_produce_an_availability_success_to_gate_pricing(): void
    {
        $resource = $this->bookableResource('Sports Hall');
        ResourceRate::factory()->for($resource)->create(['amount_minor' => 7200]);
        $equipment = Equipment::factory()->for($resource->facility->centre)->create(['quantity' => 1]);
        EquipmentAllocation::factory()->for($equipment)->create([
            'quantity' => 1,
            'starts_at' => '2026-10-05 18:00:00',
            'ends_at' => '2026-10-05 19:30:00',
        ]);

        $this->postJson(route('availability.check'), $this->payload($resource, [
            ['equipment_id' => $equipment->id, 'quantity' => 1],
        ]))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'available' => false,
                    'reasons' => ['equipment_unavailable'],
                ],
            ]);
    }

    private function bookableResource(string $name): Resource
    {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->for($centre)->create();
        $resource = Resource::factory()->for($facility)->create(['name' => $name]);
        FacilityBookableHour::factory()->for($facility)->create([
            'day_of_week' => DayOfWeek::Monday,
            'opens_at' => '08:00:00',
            'closes_at' => '21:00:00',
        ]);
        ResourceBookableHour::factory()->for($resource)->create([
            'day_of_week' => DayOfWeek::Monday,
            'opens_at' => '08:00:00',
            'closes_at' => '21:00:00',
        ]);

        return $resource;
    }

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipment
     * @return array{resource_id: int, starts_at: string, ends_at: string, equipment: list<array{equipment_id: int, quantity: int}>}
     */
    private function payload(Resource $resource, array $equipment = []): array
    {
        return [
            'resource_id' => $resource->id,
            'starts_at' => '2026-10-05 18:00:00',
            'ends_at' => '2026-10-05 19:30:00',
            'equipment' => $equipment,
        ];
    }
}
