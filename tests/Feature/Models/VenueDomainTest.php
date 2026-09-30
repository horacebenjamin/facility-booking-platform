<?php

namespace Tests\Feature\Models;

use App\Models\AllocationUnit;
use App\Models\Centre;
use App\Models\Facility;
use App\Models\Resource;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class VenueDomainTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_centre_has_its_facilities(): void
    {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->for($centre)->create();

        $this->assertTrue($centre->facilities->contains($facility));
    }

    public function test_facility_belongs_to_its_centre(): void
    {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->for($centre)->create();

        $this->assertTrue($facility->centre->is($centre));
    }

    public function test_facility_has_its_resources(): void
    {
        $facility = Facility::factory()->create();
        $resource = Resource::factory()->for($facility)->create();

        $this->assertTrue($facility->resources->contains($resource));
    }

    public function test_resource_belongs_to_its_facility(): void
    {
        $facility = Facility::factory()->create();
        $resource = Resource::factory()->for($facility)->create();

        $this->assertTrue($resource->facility->is($facility));
    }

    public function test_facility_has_its_allocation_units(): void
    {
        $facility = Facility::factory()->create();
        $allocationUnit = AllocationUnit::factory()->for($facility)->create();

        $this->assertTrue($facility->allocationUnits->contains($allocationUnit));
    }

    public function test_resource_maps_to_an_allocation_unit(): void
    {
        $facility = Facility::factory()->create();
        $resource = Resource::factory()->for($facility)->create();
        $allocationUnit = AllocationUnit::factory()->for($facility)->create();

        $resource->syncAllocationUnits($allocationUnit);

        $this->assertTrue($resource->allocationUnits->contains($allocationUnit));
        $this->assertDatabaseHas('allocation_unit_resource', [
            'resource_id' => $resource->id,
            'allocation_unit_id' => $allocationUnit->id,
        ]);
    }

    public function test_resource_can_map_to_multiple_allocation_units(): void
    {
        $facility = Facility::factory()->create();
        $resource = Resource::factory()->for($facility)->create();
        $firstAllocationUnit = AllocationUnit::factory()->for($facility)->create();
        $secondAllocationUnit = AllocationUnit::factory()->for($facility)->create();

        $resource->syncAllocationUnits($firstAllocationUnit, $secondAllocationUnit);

        $this->assertSame(
            [$firstAllocationUnit->id, $secondAllocationUnit->id],
            $resource->allocationUnits()->orderBy('allocation_units.id')->pluck('allocation_units.id')->all(),
        );
    }

    public function test_allocation_unit_can_be_mapped_to_multiple_resources(): void
    {
        $facility = Facility::factory()->create();
        $allocationUnit = AllocationUnit::factory()->for($facility)->create();
        $firstResource = Resource::factory()->for($facility)->create();
        $secondResource = Resource::factory()->for($facility)->create();

        $firstResource->syncAllocationUnits($allocationUnit);
        $secondResource->syncAllocationUnits($allocationUnit);

        $this->assertSame(
            [$firstResource->id, $secondResource->id],
            $allocationUnit->resources()->orderBy('resources.id')->pluck('resources.id')->all(),
        );
    }

    public function test_resource_rejects_an_allocation_unit_from_another_facility(): void
    {
        $resource = Resource::factory()->create();
        $allocationUnit = AllocationUnit::factory()->create();

        try {
            $resource->syncAllocationUnits($allocationUnit);

            $this->fail('Cross-facility allocation units must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('An allocation unit must belong to the resource facility.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('allocation_unit_resource', [
            'resource_id' => $resource->id,
            'allocation_unit_id' => $allocationUnit->id,
        ]);
    }

    public function test_database_rejects_a_cross_facility_allocation_mapping(): void
    {
        $resource = Resource::factory()->create();
        $allocationUnit = AllocationUnit::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('allocation_unit_resource')->insert([
            'resource_id' => $resource->id,
            'allocation_unit_id' => $allocationUnit->id,
            'facility_id' => $resource->facility_id,
        ]);
    }

    public function test_capacity_persists_as_nullable_or_positive_integer(): void
    {
        $facility = Facility::factory()->create(['capacity' => null]);
        $resource = Resource::factory()->for($facility)->create(['capacity' => 120]);

        $this->assertNull($facility->fresh()->capacity);
        $this->assertSame(120, $resource->fresh()->capacity);
    }

    public function test_database_rejects_zero_capacity(): void
    {
        $this->expectException(QueryException::class);

        Facility::factory()->create(['capacity' => 0]);
    }

    public function test_inactive_states_persist_for_venue_models(): void
    {
        $centre = Centre::factory()->inactive()->create();
        $facility = Facility::factory()->for($centre)->inactive()->create();
        $resource = Resource::factory()->for($facility)->inactive()->create();
        $allocationUnit = AllocationUnit::factory()->for($facility)->inactive()->create();

        $this->assertFalse($centre->fresh()->is_active);
        $this->assertFalse($facility->fresh()->is_active);
        $this->assertFalse($resource->fresh()->is_active);
        $this->assertFalse($allocationUnit->fresh()->is_active);
    }

    public function test_database_enforces_scoped_identifiers(): void
    {
        $centre = Centre::factory()->create();
        Facility::factory()->for($centre)->create(['slug' => 'sports-hall']);

        $this->expectException(QueryException::class);

        Facility::factory()->for($centre)->create(['slug' => 'sports-hall']);
    }

    public function test_database_enforces_unique_allocation_mappings(): void
    {
        $facility = Facility::factory()->create();
        $resource = Resource::factory()->for($facility)->create();
        $allocationUnit = AllocationUnit::factory()->for($facility)->create();

        $resource->syncAllocationUnits($allocationUnit);

        $this->expectException(QueryException::class);

        DB::table('allocation_unit_resource')->insert([
            'resource_id' => $resource->id,
            'allocation_unit_id' => $allocationUnit->id,
            'facility_id' => $facility->id,
        ]);
    }
}
