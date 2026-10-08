<?php

namespace Tests\Feature;

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
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AvailabilityAcceptanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_complete_available_request_composes_through_the_public_endpoint_without_writing_state(): void
    {
        $resource = $this->bookableResource();
        $this->attachUnit($resource, 'A');
        $equipment = Equipment::factory()->for($resource->facility->centre)->create(['quantity' => 4]);
        $counts = [
            'allocation_occupancies' => AllocationOccupancy::count(),
            'availability_blocks' => AvailabilityBlock::count(),
            'equipment_allocations' => EquipmentAllocation::count(),
        ];

        $this->postJson(route('availability.check'), $this->payload($resource, equipment: [$this->equipment($equipment, 2)]))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'available' => true,
                    'reasons' => [],
                ],
            ]);

        foreach ($counts as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
    }

    public function test_parent_child_conflicts_and_sibling_independence_compose_through_the_public_endpoint(): void
    {
        $resource = $this->bookableResource();
        $facility = $resource->facility;
        $unitA = $this->attachUnit($resource, 'A');
        $unitB = AllocationUnit::factory()->for($facility)->create(['name' => 'B', 'code' => 'B']);
        $wholeHall = Resource::factory()->for($facility)->create(['name' => 'Whole Hall', 'slug' => 'whole-hall']);
        $courtTwo = Resource::factory()->for($facility)->create(['name' => 'Court 2', 'slug' => 'court-2']);
        $wholeHall->syncAllocationUnits($unitA, $unitB);
        $courtTwo->syncAllocationUnits($unitB);
        ResourceBookableHour::factory()->for($wholeHall)->create();
        ResourceBookableHour::factory()->for($courtTwo)->create();
        $this->occupy($unitA);

        $this->postJson(route('availability.check'), $this->payload($wholeHall))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'available' => false,
                    'reasons' => ['resource_conflict'],
                ],
            ]);

        $this->postJson(route('availability.check'), $this->payload($courtTwo))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'available' => true,
                    'reasons' => [],
                ],
            ]);
    }

    public function test_setup_time_extends_operational_occupancy_through_the_public_endpoint(): void
    {
        $resource = $this->bookableResource(setupMinutes: 15);
        $unit = $this->attachUnit($resource, 'A');
        $this->occupy($unit, '2026-10-05 18:00:00', '2026-10-05 19:00:00');

        $this->postJson(route('availability.check'), $this->payload($resource, '2026-10-05 19:10:00', '2026-10-05 20:00:00'))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'available' => false,
                    'reasons' => ['resource_conflict'],
                ],
            ]);
    }

    public function test_multiple_independent_restrictions_return_stable_public_reason_codes_without_private_details(): void
    {
        $resource = $this->bookableResource();
        $block = AvailabilityBlock::factory()->forResource($resource)->create([
            'starts_at' => $this->localBookingTimeAsUtcString('2026-10-05 18:00:00'),
            'ends_at' => $this->localBookingTimeAsUtcString('2026-10-05 19:00:00'),
            'reason' => 'Staff-only maintenance instructions',
        ]);
        $equipment = Equipment::factory()->for($resource->facility->centre)->create(['quantity' => 1]);
        $allocation = EquipmentAllocation::factory()->for($equipment)->create([
            'quantity' => 1,
            'starts_at' => $this->localBookingTimeAsUtcString('2026-10-05 18:00:00'),
            'ends_at' => $this->localBookingTimeAsUtcString('2026-10-05 19:00:00'),
        ]);

        $response = $this->postJson(route('availability.check'), $this->payload($resource, equipment: [$this->equipment($equipment)]));

        $response->assertOk()
            ->assertExactJson([
                'data' => [
                    'available' => false,
                    'reasons' => ['blockout', 'equipment_unavailable'],
                ],
            ])
            ->assertDontSee((string) $block->id)
            ->assertDontSee($block->reason)
            ->assertDontSee((string) $allocation->id)
            ->assertDontSee('allocation_units')
            ->assertDontSee('expires_at');
    }

    public function test_an_expired_temporary_protection_is_ignored_at_the_explicit_evaluation_instant(): void
    {
        $resource = $this->bookableResource();
        $unit = $this->attachUnit($resource, 'A');
        $this->occupy($unit, expiresAt: '2026-10-01 12:00:00');

        $result = app(AvailabilityService::class)->check(
            $resource,
            $this->localBookingTimeAsUtcInstant('2026-10-05 18:00:00'),
            $this->localBookingTimeAsUtcInstant('2026-10-05 19:00:00'),
            evaluatedAt: CarbonImmutable::parse('2026-10-01 12:00:00', config('app.timezone')),
        );

        $this->assertTrue($result->isAvailable());
        $this->assertSame([], $result->reasons());
    }

    public function test_variable_durations_and_an_exact_closing_boundary_are_available_through_the_public_endpoint(): void
    {
        $resource = $this->bookableResource(facilityHours: ['08:00:00', '19:00:00'], resourceHours: ['08:00:00', '19:00:00']);

        $this->postJson(route('availability.check'), $this->payload($resource, '2026-10-05 17:30:00', '2026-10-05 18:47:00'))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'available' => true,
                    'reasons' => [],
                ],
            ]);

        $this->postJson(route('availability.check'), $this->payload($resource, '2026-10-05 18:00:00', '2026-10-05 19:00:00'))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'available' => true,
                    'reasons' => [],
                ],
            ]);
    }

    public function test_the_endpoint_recalculates_availability_after_relevant_state_changes(): void
    {
        $resource = $this->bookableResource();
        $unit = $this->attachUnit($resource, 'A');

        $this->postJson(route('availability.check'), $this->payload($resource))
            ->assertOk()
            ->assertJsonPath('data.available', true);

        $this->occupy($unit);

        $this->postJson(route('availability.check'), $this->payload($resource))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'available' => false,
                    'reasons' => ['resource_conflict'],
                ],
            ]);
    }

    /**
     * @param  array{0: string, 1: string}  $facilityHours
     * @param  array{0: string, 1: string}  $resourceHours
     */
    private function bookableResource(
        array $facilityHours = ['08:00:00', '21:00:00'],
        array $resourceHours = ['08:00:00', '21:00:00'],
        int $setupMinutes = 0,
    ): Resource {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->for($centre)->create();
        $resource = Resource::factory()->for($facility)->create(['setup_minutes' => $setupMinutes]);
        $this->addMondayHours($resource, $facility, $facilityHours, $resourceHours);

        return $resource;
    }

    /**
     * @param  array{0: string, 1: string}  $facilityHours
     * @param  array{0: string, 1: string}  $resourceHours
     */
    private function addMondayHours(
        Resource $resource,
        Facility $facility,
        array $facilityHours = ['08:00:00', '21:00:00'],
        array $resourceHours = ['08:00:00', '21:00:00'],
    ): void {
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
    }

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipment
     * @return array{resource_id: int, starts_at: string, ends_at: string, equipment: list<array{equipment_id: int, quantity: int}>}
     */
    private function payload(
        Resource $resource,
        string $startsAt = '2026-10-05 18:00:00',
        string $endsAt = '2026-10-05 19:00:00',
        array $equipment = [],
    ): array {
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

    private function attachUnit(Resource $resource, string $code): AllocationUnit
    {
        $unit = AllocationUnit::factory()->for($resource->facility)->create([
            'name' => $code,
            'code' => $code,
        ]);
        $resource->syncAllocationUnits($unit);

        return $unit;
    }

    private function occupy(
        AllocationUnit $unit,
        string $startsAt = '2026-10-05 18:00:00',
        string $endsAt = '2026-10-05 19:00:00',
        ?string $expiresAt = null,
    ): AllocationOccupancy {
        $occupancy = AllocationOccupancy::factory()->create([
            'starts_at' => $this->localBookingTimeAsUtcString($startsAt),
            'ends_at' => $this->localBookingTimeAsUtcString($endsAt),
            'expires_at' => $expiresAt,
        ]);
        $occupancy->allocationUnits()->attach($unit);

        return $occupancy;
    }

    private function localBookingTimeAsUtcInstant(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, (string) config('booking.local_timezone'))->utc();
    }

    private function localBookingTimeAsUtcString(string $dateTime): string
    {
        return $this->localBookingTimeAsUtcInstant($dateTime)->toDateTimeString();
    }
}
