<?php

namespace Tests\Feature\Models;

use App\Models\AllocationOccupancy;
use App\Models\AllocationUnit;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AllocationOccupancyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_occupancy_can_protect_one_allocation_unit(): void
    {
        $occupancy = AllocationOccupancy::factory()->create();
        $allocationUnit = AllocationUnit::factory()->create();

        $occupancy->allocationUnits()->attach($allocationUnit);

        $this->assertTrue($occupancy->allocationUnits->contains($allocationUnit));
        $this->assertDatabaseHas('allocation_occupancy_allocation_unit', [
            'allocation_occupancy_id' => $occupancy->id,
            'allocation_unit_id' => $allocationUnit->id,
        ]);
    }

    public function test_occupancy_can_protect_multiple_allocation_units(): void
    {
        $occupancy = AllocationOccupancy::factory()->create();
        $firstAllocationUnit = AllocationUnit::factory()->create();
        $secondAllocationUnit = AllocationUnit::factory()->create();

        $occupancy->allocationUnits()->attach([$firstAllocationUnit->id, $secondAllocationUnit->id]);

        $this->assertSame(
            [$firstAllocationUnit->id, $secondAllocationUnit->id],
            $occupancy->allocationUnits()->orderBy('allocation_units.id')->pluck('allocation_units.id')->all(),
        );
    }

    public function test_allocation_unit_can_participate_in_multiple_occupancies(): void
    {
        $allocationUnit = AllocationUnit::factory()->create();
        $firstOccupancy = AllocationOccupancy::factory()->create([
            'starts_at' => '2026-10-01 09:00:00',
            'ends_at' => '2026-10-01 10:00:00',
        ]);
        $secondOccupancy = AllocationOccupancy::factory()->create([
            'starts_at' => '2026-10-01 11:00:00',
            'ends_at' => '2026-10-01 12:00:00',
        ]);

        $allocationUnit->occupancies()->attach([$firstOccupancy->id, $secondOccupancy->id]);

        $this->assertSame(
            [$firstOccupancy->id, $secondOccupancy->id],
            $allocationUnit->occupancies()->orderBy('allocation_occupancies.id')->pluck('allocation_occupancies.id')->all(),
        );
    }

    public function test_database_rejects_duplicate_occupancy_allocation_unit_mappings(): void
    {
        $occupancy = AllocationOccupancy::factory()->create();
        $allocationUnit = AllocationUnit::factory()->create();
        $occupancy->allocationUnits()->attach($allocationUnit);

        $this->expectException(QueryException::class);

        DB::table('allocation_occupancy_allocation_unit')->insert([
            'allocation_occupancy_id' => $occupancy->id,
            'allocation_unit_id' => $allocationUnit->id,
        ]);
    }

    public function test_database_enforces_a_valid_occupancy_range(): void
    {
        $occupancy = AllocationOccupancy::factory()->create([
            'starts_at' => '2026-10-01 09:00:00',
            'ends_at' => '2026-10-01 10:00:00',
        ]);

        $this->assertModelExists($occupancy);
    }

    public function test_database_rejects_an_occupancy_range_with_equal_bounds(): void
    {
        $this->expectException(QueryException::class);

        AllocationOccupancy::factory()->create([
            'starts_at' => '2026-10-01 09:00:00',
            'ends_at' => '2026-10-01 09:00:00',
        ]);
    }

    public function test_database_rejects_an_occupancy_range_with_reversed_bounds(): void
    {
        $this->expectException(QueryException::class);

        AllocationOccupancy::factory()->create([
            'starts_at' => '2026-10-01 10:00:00',
            'ends_at' => '2026-10-01 09:00:00',
        ]);
    }

    public function test_occupancy_may_have_no_expiry(): void
    {
        $occupancy = AllocationOccupancy::factory()->create(['expires_at' => null]);

        $this->assertNull($occupancy->fresh()->expires_at);
    }

    public function test_occupancy_persists_a_supplied_expiry(): void
    {
        $occupancy = AllocationOccupancy::factory()->create([
            'expires_at' => '2026-10-01 08:30:00',
        ]);

        $this->assertSame('2026-10-01 08:30:00', $occupancy->fresh()->expires_at?->toDateTimeString());
    }

    public function test_database_rejects_an_occupancy_mapping_with_an_invalid_foreign_reference(): void
    {
        $occupancy = AllocationOccupancy::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('allocation_occupancy_allocation_unit')->insert([
            'allocation_occupancy_id' => $occupancy->id,
            'allocation_unit_id' => 999999,
        ]);
    }

    public function test_database_restricts_deleting_an_allocation_unit_with_occupancies(): void
    {
        $occupancy = AllocationOccupancy::factory()->create();
        $allocationUnit = AllocationUnit::factory()->create();
        $occupancy->allocationUnits()->attach($allocationUnit);

        $this->expectException(QueryException::class);

        $allocationUnit->delete();
    }

    public function test_database_restricts_deleting_an_occupancy_with_allocation_units(): void
    {
        $occupancy = AllocationOccupancy::factory()->create();
        $allocationUnit = AllocationUnit::factory()->create();
        $occupancy->allocationUnits()->attach($allocationUnit);

        $this->expectException(QueryException::class);

        $occupancy->delete();
    }

    public function test_occupancy_datetime_attributes_are_cast_to_carbon_instances(): void
    {
        $occupancy = AllocationOccupancy::factory()->create([
            'starts_at' => '2026-10-01 09:00:00',
            'ends_at' => '2026-10-01 10:00:00',
            'expires_at' => '2026-10-01 08:30:00',
        ])->fresh();

        $this->assertInstanceOf(CarbonInterface::class, $occupancy->starts_at);
        $this->assertInstanceOf(CarbonInterface::class, $occupancy->ends_at);
        $this->assertInstanceOf(CarbonInterface::class, $occupancy->expires_at);
        $this->assertSame('2026-10-01 09:00:00', $occupancy->starts_at->toDateTimeString());
    }
}
