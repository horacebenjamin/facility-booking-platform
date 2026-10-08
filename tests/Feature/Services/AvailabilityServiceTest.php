<?php

namespace Tests\Feature\Services;

use App\Enums\AvailabilityReason;
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
use App\Services\AvailabilityResult;
use App\Services\AvailabilityService;
use App\Services\EquipmentRequirement;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AvailabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AvailabilityService::class);
    }

    public function test_active_resource_inside_both_bookable_hour_schedules_is_available(): void
    {
        $resource = $this->bookableResource();

        $this->assertAvailable($this->check($resource));
    }

    public function test_zero_duration_request_is_invalid(): void
    {
        $resource = $this->bookableResource();

        $this->assertReasons($this->check($resource, '2026-10-05 18:00:00', '2026-10-05 18:00:00'), AvailabilityReason::InvalidPeriod);
    }

    public function test_reversed_request_is_invalid(): void
    {
        $resource = $this->bookableResource();

        $this->assertReasons($this->check($resource, '2026-10-05 19:00:00', '2026-10-05 18:00:00'), AvailabilityReason::InvalidPeriod);
    }

    public function test_inactive_centre_prevents_availability(): void
    {
        $resource = $this->bookableResource(centreActive: false);

        $this->assertReasons($this->check($resource), AvailabilityReason::InactiveCentre);
    }

    public function test_inactive_facility_prevents_availability(): void
    {
        $resource = $this->bookableResource(facilityActive: false);

        $this->assertReasons($this->check($resource), AvailabilityReason::InactiveFacility);
    }

    public function test_inactive_resource_prevents_availability(): void
    {
        $resource = $this->bookableResource(resourceActive: false);

        $this->assertReasons($this->check($resource), AvailabilityReason::InactiveResource);
    }

    public function test_inactive_facility_and_resource_return_both_reasons(): void
    {
        $resource = $this->bookableResource(facilityActive: false, resourceActive: false);

        $this->assertReasons(
            $this->check($resource),
            AvailabilityReason::InactiveFacility,
            AvailabilityReason::InactiveResource,
        );
    }

    public function test_facility_bookable_hours_are_an_independent_constraint(): void
    {
        $resource = $this->bookableResource(facilityHours: ['10:00:00', '18:00:00'], resourceHours: ['08:00:00', '21:00:00']);

        $this->assertReasons($this->check($resource, '2026-10-05 09:00:00', '2026-10-05 10:00:00'), AvailabilityReason::OutsideBookableHours);
    }

    public function test_resource_bookable_hours_are_an_independent_constraint(): void
    {
        $resource = $this->bookableResource(facilityHours: ['08:00:00', '21:00:00'], resourceHours: ['10:00:00', '18:00:00']);

        $this->assertReasons($this->check($resource, '2026-10-05 09:00:00', '2026-10-05 10:00:00'), AvailabilityReason::OutsideBookableHours);
    }

    public function test_failing_both_bookable_hour_schedules_returns_one_reason(): void
    {
        $resource = $this->bookableResource(facilityHours: ['10:00:00', '18:00:00'], resourceHours: ['11:00:00', '17:00:00']);

        $this->assertReasons($this->check($resource, '2026-10-05 09:00:00', '2026-10-05 10:00:00'), AvailabilityReason::OutsideBookableHours);
    }

    public function test_centre_block_prevents_availability(): void
    {
        $resource = $this->bookableResource();

        AvailabilityBlock::factory()->forCentre($resource->facility->centre)->create($this->periodAttributes());
        $this->assertReasons($this->check($resource), AvailabilityReason::Blockout);
    }

    public function test_facility_block_prevents_availability(): void
    {
        $resource = $this->bookableResource();

        AvailabilityBlock::factory()->forFacility($resource->facility)->create($this->periodAttributes());
        $this->assertReasons($this->check($resource), AvailabilityReason::Blockout);
    }

    public function test_resource_block_prevents_availability(): void
    {
        $resource = $this->bookableResource();

        AvailabilityBlock::factory()->forResource($resource)->create($this->periodAttributes());
        $this->assertReasons($this->check($resource), AvailabilityReason::Blockout);
    }

    public function test_direct_allocation_occupancy_conflict_prevents_availability(): void
    {
        $resource = $this->bookableResource();
        $unit = $this->attachUnit($resource);
        $this->occupy($unit);

        $this->assertReasons($this->check($resource), AvailabilityReason::ResourceConflict);
    }

    public function test_parent_child_conflicts_and_sibling_resources_remain_independent(): void
    {
        $resource = $this->bookableResource();
        $facility = $resource->facility;
        $firstUnit = $this->attachUnit($resource);
        $secondUnit = AllocationUnit::factory()->for($facility)->create();
        $parent = Resource::factory()->for($facility)->create();
        $sibling = Resource::factory()->for($facility)->create();
        $parent->syncAllocationUnits($firstUnit, $secondUnit);
        $sibling->syncAllocationUnits($secondUnit);
        ResourceBookableHour::factory()->for($parent)->create();
        ResourceBookableHour::factory()->for($sibling)->create();
        $this->occupy($firstUnit);

        $this->assertReasons($this->check($parent), AvailabilityReason::ResourceConflict);
        $this->assertAvailable($this->check($sibling));
    }

    public function test_setup_period_extends_physical_conflict_evaluation(): void
    {
        $setupResource = $this->bookableResource(setupMinutes: 15);
        $setupUnit = $this->attachUnit($setupResource);
        $this->occupy($setupUnit, '2026-10-05 18:00:00', '2026-10-05 19:00:00');

        $this->assertReasons($this->check($setupResource, '2026-10-05 19:10:00', '2026-10-05 20:00:00'), AvailabilityReason::ResourceConflict);
    }

    public function test_cleanup_period_extends_physical_conflict_evaluation(): void
    {
        $cleanupResource = $this->bookableResource(cleanupMinutes: 15);
        $cleanupUnit = $this->attachUnit($cleanupResource);
        $this->occupy($cleanupUnit, '2026-10-05 20:00:00', '2026-10-05 21:00:00');

        $this->assertReasons($this->check($cleanupResource, '2026-10-05 19:00:00', '2026-10-05 19:50:00'), AvailabilityReason::ResourceConflict);
    }

    public function test_setup_boundary_adjacent_to_occupancy_does_not_conflict(): void
    {
        $resource = $this->bookableResource(setupMinutes: 15);
        $unit = $this->attachUnit($resource);
        $this->occupy($unit, '2026-10-05 18:00:00', '2026-10-05 19:00:00');

        $this->assertAvailable($this->check($resource, '2026-10-05 19:15:00', '2026-10-05 20:00:00'));
    }

    public function test_expired_temporary_occupancy_does_not_create_a_conflict(): void
    {
        $expiredResource = $this->bookableResource();
        $expiredUnit = $this->attachUnit($expiredResource);
        $this->occupy($expiredUnit, expiresAt: '2026-10-01 12:00:00');
        $this->assertAvailable($this->check($expiredResource));
    }

    public function test_unexpired_temporary_occupancy_creates_a_conflict(): void
    {
        $protectedResource = $this->bookableResource();
        $protectedUnit = $this->attachUnit($protectedResource);
        $this->occupy($protectedUnit, expiresAt: '2026-10-01 12:00:01');

        $this->assertReasons($this->check($protectedResource), AvailabilityReason::ResourceConflict);
    }

    public function test_available_equipment_requirement_keeps_a_selection_available(): void
    {
        $resource = $this->bookableResource();
        $equipment = Equipment::factory()->for($resource->facility->centre)->create(['quantity' => 2]);

        $this->assertAvailable($this->check($resource, equipmentRequirements: [new EquipmentRequirement($equipment, 2)]));
    }

    public function test_exhausted_equipment_is_unavailable(): void
    {
        $resource = $this->bookableResource();
        $exhaustedEquipment = Equipment::factory()->for($resource->facility->centre)->create(['quantity' => 2]);
        EquipmentAllocation::factory()->for($exhaustedEquipment)->create(array_merge($this->periodAttributes(), ['quantity' => 2]));
        $this->assertReasons($this->check($resource, equipmentRequirements: [new EquipmentRequirement($exhaustedEquipment, 1)]), AvailabilityReason::EquipmentUnavailable);
    }

    public function test_inactive_equipment_is_unavailable(): void
    {
        $resource = $this->bookableResource();

        $inactiveEquipment = Equipment::factory()->for($resource->facility->centre)->inactive()->create(['quantity' => 2]);
        $this->assertReasons($this->check($resource, equipmentRequirements: [new EquipmentRequirement($inactiveEquipment, 1)]), AvailabilityReason::EquipmentUnavailable);
    }

    public function test_non_positive_equipment_quantity_is_unavailable(): void
    {
        $resource = $this->bookableResource();
        $equipment = Equipment::factory()->for($resource->facility->centre)->create(['quantity' => 2]);

        $this->assertReasons($this->check($resource, equipmentRequirements: [new EquipmentRequirement($equipment, 0)]), AvailabilityReason::EquipmentUnavailable);
    }

    public function test_equipment_from_another_centre_is_unavailable(): void
    {
        $resource = $this->bookableResource();
        $otherCentreEquipment = Equipment::factory()->create(['quantity' => 1]);
        $this->assertReasons($this->check($resource, equipmentRequirements: [new EquipmentRequirement($otherCentreEquipment, 1)]), AvailabilityReason::EquipmentUnavailable);
    }

    public function test_facility_specific_equipment_from_another_facility_is_unavailable(): void
    {
        $resource = $this->bookableResource();

        $otherFacility = Facility::factory()->for($resource->facility->centre)->create();
        $otherFacilityEquipment = Equipment::factory()->forFacility($otherFacility)->create(['quantity' => 1]);
        $this->assertReasons($this->check($resource, equipmentRequirements: [new EquipmentRequirement($otherFacilityEquipment, 1)]), AvailabilityReason::EquipmentUnavailable);
    }

    public function test_multiple_equipment_failures_are_deduplicated(): void
    {
        $resource = $this->bookableResource();
        $firstEquipment = Equipment::factory()->for($resource->facility->centre)->create(['quantity' => 1]);
        $secondEquipment = Equipment::factory()->for($resource->facility->centre)->inactive()->create(['quantity' => 1]);
        EquipmentAllocation::factory()->for($firstEquipment)->create(array_merge($this->periodAttributes(), ['quantity' => 1]));

        $this->assertReasons(
            $this->check($resource, equipmentRequirements: [
                new EquipmentRequirement($firstEquipment, 1),
                new EquipmentRequirement($secondEquipment, 1),
            ]),
            AvailabilityReason::EquipmentUnavailable,
        );
    }

    public function test_blockout_resource_conflict_and_equipment_failure_are_all_reported(): void
    {
        $resource = $this->bookableResource();
        AvailabilityBlock::factory()->forCentre($resource->facility->centre)->create($this->periodAttributes());
        $this->occupy($this->attachUnit($resource));
        $equipment = Equipment::factory()->for($resource->facility->centre)->create(['quantity' => 1]);
        EquipmentAllocation::factory()->for($equipment)->create(array_merge($this->periodAttributes(), ['quantity' => 1]));

        $this->assertReasons(
            $this->check($resource, equipmentRequirements: [new EquipmentRequirement($equipment, 1)]),
            AvailabilityReason::Blockout,
            AvailabilityReason::ResourceConflict,
            AvailabilityReason::EquipmentUnavailable,
        );
    }

    public function test_caller_carbon_instances_are_not_mutated(): void
    {
        $resource = $this->bookableResource();
        $startsAt = Carbon::parse('2026-10-05 18:00:00', 'UTC');
        $endsAt = Carbon::parse('2026-10-05 19:00:00', 'UTC');
        $evaluatedAt = Carbon::parse('2026-10-01 12:00:00', 'UTC');

        $this->service->check($resource, $startsAt, $endsAt, [], $evaluatedAt);

        $this->assertSame('2026-10-05 18:00:00', $startsAt->toDateTimeString());
        $this->assertSame('UTC', $startsAt->timezoneName);
        $this->assertSame('2026-10-05 19:00:00', $endsAt->toDateTimeString());
        $this->assertSame('UTC', $endsAt->timezoneName);
        $this->assertSame('2026-10-01 12:00:00', $evaluatedAt->toDateTimeString());
        $this->assertSame('UTC', $evaluatedAt->timezoneName);
    }

    public function test_cross_midnight_request_still_delegates_physical_conflict_evaluation(): void
    {
        $resource = $this->bookableResource();
        $unit = $this->attachUnit($resource);
        $this->occupy($unit, '2026-10-05 23:30:00', '2026-10-06 00:30:00');

        $result = $this->check($resource, '2026-10-05 23:45:00', '2026-10-06 00:15:00');

        $this->assertFalse($result->isAvailable());
        $this->assertTrue($result->hasReason(AvailabilityReason::ResourceConflict));
    }

    /**
     * @param  array{0: string, 1: string}  $facilityHours
     * @param  array{0: string, 1: string}  $resourceHours
     */
    private function bookableResource(
        bool $centreActive = true,
        bool $facilityActive = true,
        bool $resourceActive = true,
        array $facilityHours = ['08:00:00', '21:00:00'],
        array $resourceHours = ['08:00:00', '21:00:00'],
        int $setupMinutes = 0,
        int $cleanupMinutes = 0,
    ): Resource {
        $centre = Centre::factory()->create(['is_active' => $centreActive]);
        $facility = Facility::factory()->for($centre)->create(['is_active' => $facilityActive]);
        $resource = Resource::factory()->for($facility)->create([
            'is_active' => $resourceActive,
            'setup_minutes' => $setupMinutes,
            'cleanup_minutes' => $cleanupMinutes,
        ]);
        $this->addMondayHours($resource, $facility, $facilityHours, $resourceHours);

        return $resource;
    }

    /**
     * @param  array{0: string, 1: string}  $facilityHours
     * @param  array{0: string, 1: string}  $resourceHours
     */
    private function addMondayHours(Resource $resource, Facility $facility, array $facilityHours = ['08:00:00', '21:00:00'], array $resourceHours = ['08:00:00', '21:00:00']): void
    {
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
     * @param  list<EquipmentRequirement>  $equipmentRequirements
     */
    private function check(Resource $resource, string $startsAt = '2026-10-05 18:00:00', string $endsAt = '2026-10-05 19:00:00', array $equipmentRequirements = []): AvailabilityResult
    {
        return $this->service->check(
            $resource,
            $this->localBookingTimeAsUtcInstant($startsAt),
            $this->localBookingTimeAsUtcInstant($endsAt),
            $equipmentRequirements,
            CarbonImmutable::parse('2026-10-01 12:00:00', 'UTC'),
        );
    }

    private function attachUnit(Resource $resource): AllocationUnit
    {
        $unit = AllocationUnit::factory()->for($resource->facility)->create();
        $resource->syncAllocationUnits($unit);

        return $unit;
    }

    private function occupy(AllocationUnit $unit, string $startsAt = '2026-10-05 18:00:00', string $endsAt = '2026-10-05 19:00:00', ?string $expiresAt = null): AllocationOccupancy
    {
        $occupancy = AllocationOccupancy::factory()->create([
            'starts_at' => $this->localBookingTimeAsUtcInstant($startsAt)->toDateTimeString(),
            'ends_at' => $this->localBookingTimeAsUtcInstant($endsAt)->toDateTimeString(),
            'expires_at' => $expiresAt,
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
            'starts_at' => $this->localBookingTimeAsUtcInstant('2026-10-05 18:00:00')->toDateTimeString(),
            'ends_at' => $this->localBookingTimeAsUtcInstant('2026-10-05 19:00:00')->toDateTimeString(),
        ];
    }

    private function assertAvailable(AvailabilityResult $result): void
    {
        $this->assertTrue($result->isAvailable());
        $this->assertSame([], $result->reasons());
    }

    private function assertReasons(AvailabilityResult $result, AvailabilityReason ...$reasons): void
    {
        $this->assertFalse($result->isAvailable());
        $this->assertSame($reasons, $result->reasons());
    }

    private function localBookingTimeAsUtcInstant(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, (string) config('booking.local_timezone'))->utc();
    }
}
