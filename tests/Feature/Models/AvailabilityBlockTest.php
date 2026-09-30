<?php

namespace Tests\Feature\Models;

use App\Models\AvailabilityBlock;
use App\Models\Centre;
use App\Models\Facility;
use App\Models\Resource;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AvailabilityBlockTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_centre_scoped_block_persists(): void
    {
        $centre = Centre::factory()->create();
        $block = AvailabilityBlock::factory()->forCentre($centre)->create(['reason' => 'Maintenance']);

        $this->assertSame($centre->id, $block->centre->id);
        $this->assertNull($block->facility_id);
        $this->assertNull($block->resource_id);
        $this->assertDatabaseHas('availability_blocks', ['id' => $block->id, 'centre_id' => $centre->id, 'reason' => 'Maintenance']);
    }

    public function test_facility_scoped_block_persists(): void
    {
        $facility = Facility::factory()->create();
        $block = AvailabilityBlock::factory()->forFacility($facility)->create();

        $this->assertSame($facility->id, $block->facility->id);
        $this->assertNull($block->centre_id);
        $this->assertNull($block->resource_id);
        $this->assertDatabaseHas('availability_blocks', ['id' => $block->id, 'facility_id' => $facility->id]);
    }

    public function test_resource_scoped_block_persists(): void
    {
        $resource = Resource::factory()->create();
        $block = AvailabilityBlock::factory()->forResource($resource)->create();

        $this->assertSame($resource->id, $block->resource->id);
        $this->assertNull($block->centre_id);
        $this->assertNull($block->facility_id);
        $this->assertDatabaseHas('availability_blocks', ['id' => $block->id, 'resource_id' => $resource->id]);
    }

    public function test_database_rejects_a_block_without_a_scope(): void
    {
        $this->expectException(QueryException::class);

        DB::table('availability_blocks')->insert([
            'starts_at' => '2026-10-01 09:00:00',
            'ends_at' => '2026-10-01 10:00:00',
        ]);
    }

    public function test_database_rejects_a_block_with_multiple_scopes(): void
    {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->for($centre)->create();

        $this->expectException(QueryException::class);

        DB::table('availability_blocks')->insert([
            'centre_id' => $centre->id,
            'facility_id' => $facility->id,
            'starts_at' => '2026-10-01 09:00:00',
            'ends_at' => '2026-10-01 10:00:00',
        ]);
    }

    public function test_database_enforces_a_valid_block_range(): void
    {
        $block = AvailabilityBlock::factory()->create([
            'starts_at' => '2026-10-01 09:00:00',
            'ends_at' => '2026-10-01 10:00:00',
        ]);

        $this->assertModelExists($block);
    }

    public function test_database_rejects_a_block_range_with_equal_bounds(): void
    {
        $this->expectException(QueryException::class);

        AvailabilityBlock::factory()->create([
            'starts_at' => '2026-10-01 09:00:00',
            'ends_at' => '2026-10-01 09:00:00',
        ]);
    }

    public function test_database_rejects_a_block_range_with_reversed_bounds(): void
    {
        $this->expectException(QueryException::class);

        AvailabilityBlock::factory()->create([
            'starts_at' => '2026-10-01 10:00:00',
            'ends_at' => '2026-10-01 09:00:00',
        ]);
    }

    public function test_database_restricts_deleting_a_centre_with_blocks(): void
    {
        $centre = Centre::factory()->create();
        AvailabilityBlock::factory()->forCentre($centre)->create();

        $this->expectException(QueryException::class);

        $centre->delete();
    }

    public function test_database_restricts_deleting_a_facility_with_blocks(): void
    {
        $facility = Facility::factory()->create();
        AvailabilityBlock::factory()->forFacility($facility)->create();

        $this->expectException(QueryException::class);

        $facility->delete();
    }

    public function test_database_restricts_deleting_a_resource_with_blocks(): void
    {
        $resource = Resource::factory()->create();
        AvailabilityBlock::factory()->forResource($resource)->create();

        $this->expectException(QueryException::class);

        $resource->delete();
    }

    public function test_block_datetime_attributes_are_cast_to_carbon_instances(): void
    {
        $block = AvailabilityBlock::factory()->create([
            'starts_at' => '2026-10-01 09:00:00',
            'ends_at' => '2026-10-01 10:00:00',
        ])->fresh();

        $this->assertInstanceOf(CarbonInterface::class, $block->starts_at);
        $this->assertInstanceOf(CarbonInterface::class, $block->ends_at);
        $this->assertSame('2026-10-01 09:00:00', $block->starts_at->toDateTimeString());
    }
}
