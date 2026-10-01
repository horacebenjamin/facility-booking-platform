<?php

namespace Tests\Feature\Database;

use App\Enums\AvailabilityReason;
use App\Enums\DayOfWeek;
use App\Models\AllocationOccupancy;
use App\Models\AllocationUnit;
use App\Models\Centre;
use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use App\Models\Facility;
use App\Models\FacilityBookableHour;
use App\Models\Resource;
use App\Models\ResourceBookableHour;
use App\Services\AllocationConflictEvaluator;
use App\Services\AvailabilityService;
use App\Services\EquipmentAvailabilityEvaluator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MySqlAvailabilityConflictTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AllocationConflictEvaluator $allocationConflictEvaluator;

    private AvailabilityService $availabilityService;

    private EquipmentAvailabilityEvaluator $equipmentAvailabilityEvaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('mysql', DB::connection()->getDriverName());

        $this->allocationConflictEvaluator = app(AllocationConflictEvaluator::class);
        $this->availabilityService = app(AvailabilityService::class);
        $this->equipmentAvailabilityEvaluator = app(EquipmentAvailabilityEvaluator::class);
    }

    public function test_conflict_suite_uses_the_mysql_driver(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
    }

    public function test_physical_overlap_and_half_open_boundaries_use_mysql_queries(): void
    {
        $fixture = $this->allocationFixture();
        $this->occupy($fixture['a'], '2026-10-05 10:00:00', '2026-10-05 11:00:00');

        $this->assertFalse($this->hasPhysicalConflict($fixture['courtOne'], '2026-10-05 09:00:00', '2026-10-05 10:00:00'));
        $this->assertFalse($this->hasPhysicalConflict($fixture['courtOne'], '2026-10-05 11:00:00', '2026-10-05 12:00:00'));
        $this->assertTrue($this->hasPhysicalConflict($fixture['courtOne'], '2026-10-05 10:30:00', '2026-10-05 11:30:00'));
    }

    public function test_parent_child_and_sibling_resources_resolve_through_atomic_allocation_units(): void
    {
        $fixture = $this->allocationFixture();
        $this->occupy($fixture['a'], '2026-10-05 10:00:00', '2026-10-05 11:00:00');

        $this->assertTrue($this->hasPhysicalConflict($fixture['wholeHall'], '2026-10-05 10:00:00', '2026-10-05 11:00:00'));
        $this->assertTrue($this->hasPhysicalConflict($fixture['courtOne'], '2026-10-05 10:00:00', '2026-10-05 11:00:00'));
        $this->assertFalse($this->hasPhysicalConflict($fixture['courtTwo'], '2026-10-05 10:00:00', '2026-10-05 11:00:00'));
    }

    public function test_active_temporary_occupancy_conflicts_while_expired_occupancy_does_not(): void
    {
        $fixture = $this->allocationFixture();
        $this->occupy($fixture['a'], '2026-10-05 10:00:00', '2026-10-05 11:00:00', '2026-10-01 12:00:01');
        $this->occupy($fixture['b'], '2026-10-05 10:00:00', '2026-10-05 11:00:00', '2026-10-01 12:00:00');

        $this->assertTrue($this->hasPhysicalConflict($fixture['courtOne'], '2026-10-05 10:00:00', '2026-10-05 11:00:00'));
        $this->assertFalse($this->hasPhysicalConflict($fixture['courtTwo'], '2026-10-05 10:00:00', '2026-10-05 11:00:00'));
    }

    public function test_equipment_availability_aggregates_only_overlapping_mysql_rows(): void
    {
        $equipment = Equipment::factory()->create(['quantity' => 4]);
        $this->allocateEquipment($equipment, 2, '2026-10-05 10:00:00', '2026-10-05 11:00:00');
        $this->allocateEquipment($equipment, 1, '2026-10-05 10:15:00', '2026-10-05 11:15:00');
        $this->allocateEquipment($equipment, 4, '2026-10-05 11:00:00', '2026-10-05 12:00:00');

        $this->assertTrue($this->hasEquipmentAvailable($equipment, 1, '2026-10-05 10:00:00', '2026-10-05 11:00:00'));
        $this->assertFalse($this->hasEquipmentAvailable($equipment, 2, '2026-10-05 10:00:00', '2026-10-05 11:00:00'));
    }

    public function test_expired_equipment_allocations_do_not_consume_mysql_availability(): void
    {
        $equipment = Equipment::factory()->create(['quantity' => 4]);
        $this->allocateEquipment($equipment, 3, '2026-10-05 10:00:00', '2026-10-05 11:00:00', '2026-10-01 12:00:00');
        $this->allocateEquipment($equipment, 1, '2026-10-05 10:00:00', '2026-10-05 11:00:00', '2026-10-01 12:00:01');

        $this->assertTrue($this->hasEquipmentAvailable($equipment, 3, '2026-10-05 10:00:00', '2026-10-05 11:00:00'));
        $this->assertFalse($this->hasEquipmentAvailable($equipment, 4, '2026-10-05 10:00:00', '2026-10-05 11:00:00'));
    }

    public function test_setup_and_cleanup_expansion_detects_mysql_physical_conflicts(): void
    {
        $resource = $this->bookableResource(setupMinutes: 15, cleanupMinutes: 15);
        $unit = AllocationUnit::factory()->for($resource->facility)->create(['code' => 'A']);
        $resource->syncAllocationUnits($unit);
        $this->occupy($unit, '2026-10-05 18:00:00', '2026-10-05 19:00:00');

        $result = $this->availabilityService->check(
            $resource,
            $this->at('2026-10-05 19:10:00'),
            $this->at('2026-10-05 20:00:00'),
            [],
            $this->at('2026-10-01 12:00:00'),
        );

        $this->assertTrue($result->hasReason(AvailabilityReason::ResourceConflict));
    }

    /**
     * @return array{a: AllocationUnit, b: AllocationUnit, wholeHall: \App\Models\Resource, courtOne: \App\Models\Resource, courtTwo: \App\Models\Resource}
     */
    private function allocationFixture(): array
    {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->for($centre)->create();
        $a = AllocationUnit::factory()->for($facility)->create(['name' => 'A', 'code' => 'A']);
        $b = AllocationUnit::factory()->for($facility)->create(['name' => 'B', 'code' => 'B']);
        $wholeHall = Resource::factory()->for($facility)->create(['name' => 'Whole Hall', 'slug' => 'whole-hall']);
        $courtOne = Resource::factory()->for($facility)->create(['name' => 'Court 1', 'slug' => 'court-1']);
        $courtTwo = Resource::factory()->for($facility)->create(['name' => 'Court 2', 'slug' => 'court-2']);

        $wholeHall->syncAllocationUnits($a, $b);
        $courtOne->syncAllocationUnits($a);
        $courtTwo->syncAllocationUnits($b);

        return compact('a', 'b', 'wholeHall', 'courtOne', 'courtTwo');
    }

    private function bookableResource(int $setupMinutes, int $cleanupMinutes): Resource
    {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->for($centre)->create();
        $resource = Resource::factory()->for($facility)->create([
            'setup_minutes' => $setupMinutes,
            'cleanup_minutes' => $cleanupMinutes,
        ]);

        FacilityBookableHour::factory()->for($facility)->create([
            'day_of_week' => DayOfWeek::Monday,
            'opens_at' => '08:00:00',
            'closes_at' => '22:00:00',
        ]);
        ResourceBookableHour::factory()->for($resource)->create([
            'day_of_week' => DayOfWeek::Monday,
            'opens_at' => '08:00:00',
            'closes_at' => '22:00:00',
        ]);

        return $resource;
    }

    private function occupy(AllocationUnit $allocationUnit, string $startsAt, string $endsAt, ?string $expiresAt = null): AllocationOccupancy
    {
        $occupancy = AllocationOccupancy::factory()->create([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'expires_at' => $expiresAt,
        ]);
        $occupancy->allocationUnits()->attach($allocationUnit);

        return $occupancy;
    }

    private function allocateEquipment(Equipment $equipment, int $quantity, string $startsAt, string $endsAt, ?string $expiresAt = null): EquipmentAllocation
    {
        return EquipmentAllocation::factory()->for($equipment)->create([
            'quantity' => $quantity,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'expires_at' => $expiresAt,
        ]);
    }

    private function hasPhysicalConflict(Resource $resource, string $startsAt, string $endsAt): bool
    {
        return $this->allocationConflictEvaluator->hasConflict(
            $resource,
            $this->at($startsAt),
            $this->at($endsAt),
            $this->at('2026-10-01 12:00:00'),
        );
    }

    private function hasEquipmentAvailable(Equipment $equipment, int $quantity, string $startsAt, string $endsAt): bool
    {
        return $this->equipmentAvailabilityEvaluator->isAvailable(
            $equipment,
            $quantity,
            $this->at($startsAt),
            $this->at($endsAt),
            $this->at('2026-10-01 12:00:00'),
        );
    }

    private function at(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, config('app.timezone'));
    }
}
