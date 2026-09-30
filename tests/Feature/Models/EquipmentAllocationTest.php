<?php

namespace Tests\Feature\Models;

use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EquipmentAllocationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_allocation_belongs_to_equipment(): void
    {
        $equipment = Equipment::factory()->create();
        $allocation = EquipmentAllocation::factory()->for($equipment)->create();

        $this->assertTrue($allocation->equipment->is($equipment));
    }

    public function test_equipment_has_many_allocations(): void
    {
        $equipment = Equipment::factory()->create();
        EquipmentAllocation::factory()->count(2)->for($equipment)->create();

        $this->assertCount(2, $equipment->allocations);
    }

    public function test_database_persists_a_positive_quantity(): void
    {
        $allocation = EquipmentAllocation::factory()->create(['quantity' => 4]);

        $this->assertSame(4, $allocation->fresh()->quantity);
    }

    public function test_database_rejects_zero_quantity(): void
    {
        $this->expectException(QueryException::class);

        EquipmentAllocation::factory()->create(['quantity' => 0]);
    }

    public function test_database_rejects_negative_quantity(): void
    {
        $this->expectException(QueryException::class);

        EquipmentAllocation::factory()->create(['quantity' => -1]);
    }

    public function test_database_enforces_an_increasing_time_range(): void
    {
        $allocation = EquipmentAllocation::factory()->create([
            'starts_at' => '2026-10-01 09:00:00',
            'ends_at' => '2026-10-01 10:00:00',
        ]);

        $this->assertModelExists($allocation);
    }

    public function test_database_rejects_equal_time_bounds(): void
    {
        $this->expectException(QueryException::class);

        EquipmentAllocation::factory()->create([
            'starts_at' => '2026-10-01 09:00:00',
            'ends_at' => '2026-10-01 09:00:00',
        ]);
    }

    public function test_database_rejects_reversed_time_bounds(): void
    {
        $this->expectException(QueryException::class);

        EquipmentAllocation::factory()->create([
            'starts_at' => '2026-10-01 10:00:00',
            'ends_at' => '2026-10-01 09:00:00',
        ]);
    }

    public function test_expiry_may_be_null(): void
    {
        $allocation = EquipmentAllocation::factory()->create(['expires_at' => null]);

        $this->assertNull($allocation->fresh()->expires_at);
    }

    public function test_expiry_persists_when_supplied(): void
    {
        $allocation = EquipmentAllocation::factory()->create(['expires_at' => '2026-10-01 08:30:00']);

        $this->assertSame('2026-10-01 08:30:00', $allocation->fresh()->expires_at?->toDateTimeString());
    }

    public function test_database_rejects_an_invalid_equipment_foreign_key(): void
    {
        $this->expectException(QueryException::class);

        DB::table('equipment_allocations')->insert([
            'equipment_id' => 999999,
            'quantity' => 1,
            'starts_at' => '2026-10-01 09:00:00',
            'ends_at' => '2026-10-01 10:00:00',
        ]);
    }

    public function test_database_restricts_deleting_equipment_with_allocations(): void
    {
        $equipment = Equipment::factory()->create();
        EquipmentAllocation::factory()->for($equipment)->create();

        $this->expectException(QueryException::class);

        $equipment->delete();
    }

    public function test_datetime_attributes_are_cast_to_carbon_instances(): void
    {
        $allocation = EquipmentAllocation::factory()->create([
            'starts_at' => '2026-10-01 09:00:00',
            'ends_at' => '2026-10-01 10:00:00',
            'expires_at' => '2026-10-01 08:30:00',
        ])->fresh();

        $this->assertInstanceOf(CarbonInterface::class, $allocation->starts_at);
        $this->assertInstanceOf(CarbonInterface::class, $allocation->ends_at);
        $this->assertInstanceOf(CarbonInterface::class, $allocation->expires_at);
        $this->assertSame('2026-10-01 09:00:00', $allocation->starts_at->toDateTimeString());
    }
}
