<?php

namespace Tests\Feature\Http;

use App\Enums\DayOfWeek;
use App\Models\AllocationOccupancy;
use App\Models\AllocationUnit;
use App\Models\AvailabilityBlock;
use App\Models\Centre;
use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use App\Models\Facility;
use App\Models\FacilityBookableHour;
use App\Models\Resource;
use App\Models\ResourceBookableHour;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckAvailabilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_named_route_accepts_post_requests(): void
    {
        $route = Route::getRoutes()->getByName('availability.check');

        $this->assertNotNull($route);
        $this->assertSame(['POST'], $route->methods());
        $this->assertSame('availability/check', $route->uri());
    }

    public function test_returns_an_available_result_without_authentication(): void
    {
        $resource = $this->bookableResource();

        $this->postJson(route('availability.check'), $this->payload($resource))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'available' => true,
                    'reasons' => [],
                ],
            ]);
    }

    public function test_returns_resource_conflict_without_private_occupancy_details(): void
    {
        $resource = $this->bookableResource();
        $occupancy = $this->occupy($this->attachUnit($resource), '2026-10-05 17:30:00', '2026-10-05 19:30:00');

        $response = $this->postJson(route('availability.check'), $this->payload($resource));

        $response->assertOk()
            ->assertExactJson([
                'data' => [
                    'available' => false,
                    'reasons' => ['resource_conflict'],
                ],
            ])
            ->assertDontSee((string) $occupancy->id)
            ->assertDontSee($occupancy->starts_at->toDateTimeString())
            ->assertDontSee($occupancy->ends_at->toDateTimeString())
            ->assertDontSee('customer')
            ->assertDontSee('organisation')
            ->assertDontSee('allocation_occupancies');
    }

    public function test_returns_blockout_for_a_blocked_resource(): void
    {
        $resource = $this->bookableResource();
        AvailabilityBlock::factory()->forResource($resource)->create($this->periodAttributes());

        $this->postJson(route('availability.check'), $this->payload($resource))
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.reasons', ['blockout']);
    }

    public function test_returns_outside_bookable_hours(): void
    {
        $resource = $this->bookableResource(['10:00:00', '18:00:00'], ['10:00:00', '18:00:00']);

        $this->postJson(route('availability.check'), $this->payload($resource, startsAt: '2026-10-05 09:00:00', endsAt: '2026-10-05 10:00:00'))
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.reasons', ['outside_bookable_hours']);
    }

    public function test_returns_inactive_resource(): void
    {
        $resource = $this->bookableResource(resourceActive: false);

        $this->postJson(route('availability.check'), $this->payload($resource))
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.reasons', ['inactive_resource']);
    }

    public function test_returns_equipment_unavailable_when_equipment_is_exhausted(): void
    {
        $resource = $this->bookableResource();
        $equipment = Equipment::factory()->for($resource->facility->centre)->create(['quantity' => 1]);
        EquipmentAllocation::factory()->for($equipment)->create(array_merge($this->periodAttributes(), ['quantity' => 1]));

        $this->postJson(route('availability.check'), $this->payload($resource, equipment: [$this->equipment($equipment)]))
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.reasons', ['equipment_unavailable']);
    }

    public function test_returns_multiple_reason_codes_as_stable_strings(): void
    {
        $resource = $this->bookableResource();
        $equipment = Equipment::factory()->for($resource->facility->centre)->create(['quantity' => 1]);
        AvailabilityBlock::factory()->forCentre($resource->facility->centre)->create($this->periodAttributes());
        $this->occupy($this->attachUnit($resource));
        EquipmentAllocation::factory()->for($equipment)->create(array_merge($this->periodAttributes(), ['quantity' => 1]));

        $this->postJson(route('availability.check'), $this->payload($resource, equipment: [$this->equipment($equipment)]))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'available' => false,
                    'reasons' => ['blockout', 'resource_conflict', 'equipment_unavailable'],
                ],
            ]);
    }

    #[DataProvider('malformedDateFields')]
    public function test_returns_422_for_a_malformed_datetime(string $field): void
    {
        $resource = $this->bookableResource();
        $payload = $this->payload($resource);
        $payload[$field] = 'not-a-date';

        $this->postJson(route('availability.check'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedDateFields(): array
    {
        return [
            'starts_at' => ['starts_at'],
            'ends_at' => ['ends_at'],
        ];
    }

    public function test_returns_422_when_end_is_not_after_start(): void
    {
        $resource = $this->bookableResource();

        $this->postJson(route('availability.check'), $this->payload($resource, endsAt: '2026-10-05 18:00:00'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_at');
    }

    public function test_returns_422_when_resource_id_is_missing(): void
    {
        $this->postJson(route('availability.check'), [
            'starts_at' => '2026-10-05 18:00:00',
            'ends_at' => '2026-10-05 19:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('resource_id');
    }

    public function test_returns_422_when_resource_id_is_unknown(): void
    {
        $this->postJson(route('availability.check'), [
            'resource_id' => 999999,
            'starts_at' => '2026-10-05 18:00:00',
            'ends_at' => '2026-10-05 19:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('resource_id');
    }

    #[DataProvider('invalidEquipmentQuantities')]
    public function test_returns_422_for_an_invalid_equipment_quantity(int $quantity): void
    {
        $resource = $this->bookableResource();
        $equipment = Equipment::factory()->for($resource->facility->centre)->create();

        $this->postJson(route('availability.check'), $this->payload($resource, equipment: [$this->equipment($equipment, $quantity)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('equipment.0.quantity');
    }

    /**
     * @return array<string, array{int}>
     */
    public static function invalidEquipmentQuantities(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    public function test_returns_422_when_equipment_id_is_unknown(): void
    {
        $resource = $this->bookableResource();

        $this->postJson(route('availability.check'), $this->payload($resource, equipment: [['equipment_id' => 999999, 'quantity' => 1]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('equipment.0.equipment_id');
    }

    public function test_returns_422_for_duplicate_equipment_ids(): void
    {
        $resource = $this->bookableResource();
        $equipment = Equipment::factory()->for($resource->facility->centre)->create();

        $this->postJson(route('availability.check'), $this->payload($resource, equipment: [
            $this->equipment($equipment, 1),
            $this->equipment($equipment, 2),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('equipment.1.equipment_id');
    }

    public function test_returns_equipment_unavailable_for_equipment_from_another_centre(): void
    {
        $resource = $this->bookableResource();
        $equipment = Equipment::factory()->create(['quantity' => 1]);

        $this->postJson(route('availability.check'), $this->payload($resource, equipment: [$this->equipment($equipment)]))
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.reasons', ['equipment_unavailable']);
    }

    public function test_returns_equipment_unavailable_for_facility_incompatible_equipment(): void
    {
        $resource = $this->bookableResource();
        $otherFacility = Facility::factory()->for($resource->facility->centre)->create();
        $equipment = Equipment::factory()->forFacility($otherFacility)->create(['quantity' => 1]);

        $this->postJson(route('availability.check'), $this->payload($resource, equipment: [$this->equipment($equipment)]))
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.reasons', ['equipment_unavailable']);
    }

    public function test_maps_multiple_valid_equipment_requirements(): void
    {
        $resource = $this->bookableResource();
        $firstEquipment = Equipment::factory()->for($resource->facility->centre)->create(['quantity' => 1]);
        $secondEquipment = Equipment::factory()->for($resource->facility->centre)->create(['quantity' => 2]);

        $this->postJson(route('availability.check'), $this->payload($resource, equipment: [
            $this->equipment($firstEquipment),
            $this->equipment($secondEquipment, 2),
        ]))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'available' => true,
                    'reasons' => [],
                ],
            ]);
    }

    public function test_is_read_only_and_repeated_checks_return_the_same_structure(): void
    {
        $resource = $this->bookableResource();
        $payload = $this->payload($resource);
        $counts = [
            'allocation_occupancies' => AllocationOccupancy::count(),
            'equipment_allocations' => EquipmentAllocation::count(),
            'availability_blocks' => AvailabilityBlock::count(),
        ];

        $firstResponse = $this->postJson(route('availability.check'), $payload);
        $secondResponse = $this->postJson(route('availability.check'), $payload);

        $firstResponse->assertOk()->assertExactJson([
            'data' => [
                'available' => true,
                'reasons' => [],
            ],
        ]);
        $secondResponse->assertOk()->assertExactJson($firstResponse->json());

        foreach ($counts as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
    }

    /**
     * @param  array{0: string, 1: string}  $facilityHours
     * @param  array{0: string, 1: string}  $resourceHours
     */
    private function bookableResource(
        array $facilityHours = ['08:00:00', '21:00:00'],
        array $resourceHours = ['08:00:00', '21:00:00'],
        bool $resourceActive = true,
    ): Resource {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->for($centre)->create();
        $resource = Resource::factory()->for($facility)->create(['is_active' => $resourceActive]);
        FacilityBookableHour::factory()->for($facility)->create([
            'day_of_week' => DayOfWeek::Monday,
            'opens_at' => $facilityHours[0],
            'closes_at' => $facilityHours[1],
        ]);
        ResourceBookableHour::factory()->for($resource)->create([
            'day_of_week' => DayOfWeek::Monday,
            'opens_at' => $resourceHours[0],
            'closes_at' => $resourceHours[1],
        ]);

        return $resource;
    }

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipment
     * @return array{resource_id: int, starts_at: string, ends_at: string, equipment: list<array{equipment_id: int, quantity: int}>}
     */
    private function payload(Resource $resource, string $startsAt = '2026-10-05 18:00:00', string $endsAt = '2026-10-05 19:00:00', array $equipment = []): array
    {
        return [
            'resource_id' => $resource->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'equipment' => $equipment,
        ];
    }

    /**
     * @return array{equipment_id: int, quantity: int}
     */
    private function equipment(Equipment $equipment, int $quantity = 1): array
    {
        return [
            'equipment_id' => $equipment->id,
            'quantity' => $quantity,
        ];
    }

    private function attachUnit(Resource $resource): AllocationUnit
    {
        $unit = AllocationUnit::factory()->for($resource->facility)->create();
        $resource->syncAllocationUnits($unit);

        return $unit;
    }

    private function occupy(AllocationUnit $unit, string $startsAt = '2026-10-05 18:00:00', string $endsAt = '2026-10-05 19:00:00'): AllocationOccupancy
    {
        $occupancy = AllocationOccupancy::factory()->create([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        $occupancy->allocationUnits()->attach($unit);

        return $occupancy;
    }

    /**
     * @return array{starts_at: string, ends_at: string}
     */
    private function periodAttributes(): array
    {
        return [
            'starts_at' => '2026-10-05 18:00:00',
            'ends_at' => '2026-10-05 19:00:00',
        ];
    }
}
