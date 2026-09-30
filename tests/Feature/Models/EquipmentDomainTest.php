<?php

namespace Tests\Feature\Models;

use App\Models\Centre;
use App\Models\Equipment;
use App\Models\Facility;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EquipmentDomainTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_centre_has_its_equipment(): void
    {
        $centre = Centre::factory()->create();
        $equipment = Equipment::factory()->for($centre)->create();

        $this->assertTrue($centre->equipment->contains($equipment));
    }

    public function test_equipment_belongs_to_its_centre(): void
    {
        $centre = Centre::factory()->create();
        $equipment = Equipment::factory()->for($centre)->create();

        $this->assertTrue($equipment->centre->is($centre));
    }

    public function test_facility_has_its_equipment(): void
    {
        $facility = Facility::factory()->create();
        $equipment = Equipment::factory()->forFacility($facility)->create();

        $this->assertTrue($facility->equipment->contains($equipment));
    }

    public function test_equipment_may_belong_to_a_facility(): void
    {
        $facility = Facility::factory()->create();
        $equipment = Equipment::factory()->forFacility($facility)->create();

        $this->assertTrue($equipment->facility->is($facility));
    }

    public function test_equipment_can_exist_at_centre_level_without_a_facility(): void
    {
        $equipment = Equipment::factory()->create();

        $this->assertNull($equipment->facility_id);
        $this->assertNull($equipment->facility);
    }

    public function test_facility_specific_equipment_uses_the_facility_centre(): void
    {
        $facility = Facility::factory()->create();
        $equipment = Equipment::factory()->forFacility($facility)->create();

        $this->assertSame($facility->centre_id, $equipment->centre_id);
    }

    public function test_equipment_rejects_a_facility_from_another_centre(): void
    {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->create();

        $this->expectException(QueryException::class);

        Equipment::factory()->for($centre)->create([
            'facility_id' => $facility->id,
        ]);
    }

    public function test_database_rejects_a_forged_cross_centre_equipment_facility_relationship(): void
    {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('equipment')->insert([
            'centre_id' => $centre->id,
            'facility_id' => $facility->id,
            'name' => 'Forged relationship',
            'quantity' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_quantity_persists_as_an_integer(): void
    {
        $equipment = Equipment::factory()->create(['quantity' => 24]);

        $this->assertSame(24, $equipment->fresh()->quantity);
    }

    public function test_zero_quantity_is_valid_for_equipment_configuration(): void
    {
        $equipment = Equipment::factory()->create(['quantity' => 0]);

        $this->assertSame(0, $equipment->fresh()->quantity);
    }

    public function test_database_rejects_negative_quantity(): void
    {
        $this->expectException(QueryException::class);

        Equipment::factory()->create(['quantity' => -1]);
    }

    public function test_inactive_equipment_state_persists(): void
    {
        $equipment = Equipment::factory()->inactive()->create();

        $this->assertFalse($equipment->fresh()->is_active);
    }

    public function test_database_rejects_equipment_without_a_centre(): void
    {
        $this->expectException(QueryException::class);

        DB::table('equipment')->insert([
            'centre_id' => 999999,
            'facility_id' => null,
            'name' => 'Orphaned equipment',
            'quantity' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
