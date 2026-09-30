<?php

namespace Tests\Feature\Services;

use App\Models\AllocationOccupancy;
use App\Models\AllocationUnit;
use App\Models\Centre;
use App\Models\Facility;
use App\Models\Resource;
use App\Services\AllocationConflictEvaluator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AllocationConflictEvaluatorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AllocationConflictEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluator = new AllocationConflictEvaluator;
    }

    public function test_resource_with_no_occupancy_has_no_conflict(): void
    {
        $fixture = $this->sportsHallFixture();

        $hasConflict = $this->evaluator->hasConflict($fixture['courtOne'], $this->at('2026-10-15 18:00:00'), $this->at('2026-10-15 19:00:00'));

        $this->assertFalse($hasConflict);
    }

    public function test_exactly_matching_occupancy_conflicts(): void
    {
        $fixture = $this->sportsHallFixture();
        $this->protect($fixture['a'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $hasConflict = $this->evaluator->hasConflict($fixture['courtOne'], $this->at('2026-10-15 18:00:00'), $this->at('2026-10-15 19:00:00'));

        $this->assertTrue($hasConflict);
    }

    public function test_request_starting_inside_an_occupancy_conflicts(): void
    {
        $fixture = $this->sportsHallFixture();
        $this->protect($fixture['a'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $hasConflict = $this->evaluator->hasConflict($fixture['courtOne'], $this->at('2026-10-15 18:30:00'), $this->at('2026-10-15 19:30:00'));

        $this->assertTrue($hasConflict);
    }

    public function test_request_ending_inside_an_occupancy_conflicts(): void
    {
        $fixture = $this->sportsHallFixture();
        $this->protect($fixture['a'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $hasConflict = $this->evaluator->hasConflict($fixture['courtOne'], $this->at('2026-10-15 17:30:00'), $this->at('2026-10-15 18:30:00'));

        $this->assertTrue($hasConflict);
    }

    public function test_request_containing_an_occupancy_conflicts(): void
    {
        $fixture = $this->sportsHallFixture();
        $this->protect($fixture['a'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $hasConflict = $this->evaluator->hasConflict($fixture['courtOne'], $this->at('2026-10-15 17:00:00'), $this->at('2026-10-15 20:00:00'));

        $this->assertTrue($hasConflict);
    }

    public function test_request_contained_by_an_occupancy_conflicts(): void
    {
        $fixture = $this->sportsHallFixture();
        $this->protect($fixture['a'], '2026-10-15 18:00:00', '2026-10-15 20:00:00');

        $hasConflict = $this->evaluator->hasConflict($fixture['courtOne'], $this->at('2026-10-15 18:30:00'), $this->at('2026-10-15 19:00:00'));

        $this->assertTrue($hasConflict);
    }

    public function test_request_ending_when_an_occupancy_begins_has_no_conflict(): void
    {
        $fixture = $this->sportsHallFixture();
        $this->protect($fixture['a'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $hasConflict = $this->evaluator->hasConflict($fixture['courtOne'], $this->at('2026-10-15 17:00:00'), $this->at('2026-10-15 18:00:00'));

        $this->assertFalse($hasConflict);
    }

    public function test_request_starting_when_an_occupancy_ends_has_no_conflict(): void
    {
        $fixture = $this->sportsHallFixture();
        $this->protect($fixture['a'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $hasConflict = $this->evaluator->hasConflict($fixture['courtOne'], $this->at('2026-10-15 19:00:00'), $this->at('2026-10-15 20:00:00'));

        $this->assertFalse($hasConflict);
    }

    public function test_cross_midnight_request_can_detect_a_conflict(): void
    {
        $fixture = $this->sportsHallFixture();
        $this->protect($fixture['a'], '2026-10-15 23:30:00', '2026-10-16 00:30:00');

        $hasConflict = $this->evaluator->hasConflict($fixture['courtOne'], $this->at('2026-10-15 23:45:00'), $this->at('2026-10-16 00:15:00'));

        $this->assertTrue($hasConflict);
    }

    public function test_occupancy_on_an_unrelated_allocation_unit_has_no_conflict(): void
    {
        $fixture = $this->sportsHallFixture();
        $unrelatedUnit = AllocationUnit::factory()->create();
        $this->protect($unrelatedUnit, '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $hasConflict = $this->evaluator->hasConflict($fixture['courtOne'], $this->at('2026-10-15 18:00:00'), $this->at('2026-10-15 19:00:00'));

        $this->assertFalse($hasConflict);
    }

    public function test_resource_with_multiple_allocation_units_conflicts_when_any_unit_is_occupied_in_one_query(): void
    {
        $fixture = $this->sportsHallFixture();
        $this->protect($fixture['d'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');
        DB::flushQueryLog();
        DB::enableQueryLog();

        $hasConflict = $this->evaluator->hasConflict($fixture['wholeHall'], $this->at('2026-10-15 18:00:00'), $this->at('2026-10-15 19:00:00'));

        $this->assertTrue($hasConflict);
        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_whole_facility_resource_conflicts_when_one_child_unit_is_occupied(): void
    {
        $fixture = $this->sportsHallFixture();
        $this->protect($fixture['a'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $hasConflict = $this->evaluator->hasConflict($fixture['wholeHall'], $this->at('2026-10-15 18:00:00'), $this->at('2026-10-15 19:00:00'));

        $this->assertTrue($hasConflict);
    }

    public function test_child_resource_conflicts_when_whole_facility_occupancy_protects_all_its_units(): void
    {
        $fixture = $this->sportsHallFixture();
        $this->protect($fixture['a'], '2026-10-15 18:00:00', '2026-10-15 19:00:00', null, $fixture['b'], $fixture['c'], $fixture['d']);

        $courtOneHasConflict = $this->evaluator->hasConflict($fixture['courtOne'], $this->at('2026-10-15 18:00:00'), $this->at('2026-10-15 19:00:00'));
        $courtTwoHasConflict = $this->evaluator->hasConflict($fixture['courtTwo'], $this->at('2026-10-15 18:00:00'), $this->at('2026-10-15 19:00:00'));

        $this->assertTrue($courtOneHasConflict);
        $this->assertTrue($courtTwoHasConflict);
    }

    public function test_sibling_resource_has_no_conflict_when_its_allocation_unit_is_not_occupied(): void
    {
        $fixture = $this->sportsHallFixture();
        $this->protect($fixture['a'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $hasConflict = $this->evaluator->hasConflict($fixture['courtTwo'], $this->at('2026-10-15 18:00:00'), $this->at('2026-10-15 19:00:00'));

        $this->assertFalse($hasConflict);
    }

    public function test_non_expiring_occupancy_blocks(): void
    {
        $fixture = $this->sportsHallFixture();
        $this->protect($fixture['a'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $hasConflict = $this->evaluator->hasConflict($fixture['courtOne'], $this->at('2026-10-15 18:00:00'), $this->at('2026-10-15 19:00:00'), $this->at('2026-10-10 12:00:00'));

        $this->assertTrue($hasConflict);
    }

    public function test_unexpired_temporary_occupancy_blocks(): void
    {
        $fixture = $this->sportsHallFixture();
        $this->protect($fixture['a'], '2026-10-15 18:00:00', '2026-10-15 19:00:00', '2026-10-10 13:00:00');

        $hasConflict = $this->evaluator->hasConflict($fixture['courtOne'], $this->at('2026-10-15 18:00:00'), $this->at('2026-10-15 19:00:00'), $this->at('2026-10-10 12:00:00'));

        $this->assertTrue($hasConflict);
    }

    public function test_expired_temporary_occupancy_does_not_block(): void
    {
        $fixture = $this->sportsHallFixture();
        $this->protect($fixture['a'], '2026-10-15 18:00:00', '2026-10-15 19:00:00', '2026-10-10 11:59:00');

        $hasConflict = $this->evaluator->hasConflict($fixture['courtOne'], $this->at('2026-10-15 18:00:00'), $this->at('2026-10-15 19:00:00'), $this->at('2026-10-10 12:00:00'));

        $this->assertFalse($hasConflict);
    }

    public function test_temporary_occupancy_expiring_at_the_evaluation_instant_does_not_block(): void
    {
        $fixture = $this->sportsHallFixture();
        $this->protect($fixture['a'], '2026-10-15 18:00:00', '2026-10-15 19:00:00', '2026-10-10 12:00:00');

        $hasConflict = $this->evaluator->hasConflict($fixture['courtOne'], $this->at('2026-10-15 18:00:00'), $this->at('2026-10-15 19:00:00'), $this->at('2026-10-10 12:00:00'));

        $this->assertFalse($hasConflict);
    }

    public function test_resource_with_no_allocation_units_has_no_conflict(): void
    {
        $resource = Resource::factory()->create();

        $hasConflict = $this->evaluator->hasConflict($resource, $this->at('2026-10-15 18:00:00'), $this->at('2026-10-15 19:00:00'));

        $this->assertFalse($hasConflict);
    }

    public function test_zero_duration_request_has_no_conflict(): void
    {
        $fixture = $this->sportsHallFixture();
        $this->protect($fixture['a'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $hasConflict = $this->evaluator->hasConflict($fixture['courtOne'], $this->at('2026-10-15 18:00:00'), $this->at('2026-10-15 18:00:00'));

        $this->assertFalse($hasConflict);
    }

    public function test_reversed_request_has_no_conflict(): void
    {
        $fixture = $this->sportsHallFixture();
        $this->protect($fixture['a'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $hasConflict = $this->evaluator->hasConflict($fixture['courtOne'], $this->at('2026-10-15 19:00:00'), $this->at('2026-10-15 18:00:00'));

        $this->assertFalse($hasConflict);
    }

    /**
     * @return array{a: AllocationUnit, b: AllocationUnit, c: AllocationUnit, d: AllocationUnit, wholeHall: \App\Models\Resource, courtOne: \App\Models\Resource, courtTwo: \App\Models\Resource}
     */
    private function sportsHallFixture(): array
    {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->for($centre)->create(['name' => 'Sports Hall', 'slug' => 'sports-hall']);
        $a = AllocationUnit::factory()->for($facility)->create(['name' => 'A', 'code' => 'A']);
        $b = AllocationUnit::factory()->for($facility)->create(['name' => 'B', 'code' => 'B']);
        $c = AllocationUnit::factory()->for($facility)->create(['name' => 'C', 'code' => 'C']);
        $d = AllocationUnit::factory()->for($facility)->create(['name' => 'D', 'code' => 'D']);
        $wholeHall = Resource::factory()->for($facility)->create(['name' => 'Whole Hall', 'slug' => 'whole-hall']);
        $courtOne = Resource::factory()->for($facility)->create(['name' => 'Court 1', 'slug' => 'court-1']);
        $courtTwo = Resource::factory()->for($facility)->create(['name' => 'Court 2', 'slug' => 'court-2']);

        $wholeHall->syncAllocationUnits($a, $b, $c, $d);
        $courtOne->syncAllocationUnits($a);
        $courtTwo->syncAllocationUnits($b);

        return compact('a', 'b', 'c', 'd', 'wholeHall', 'courtOne', 'courtTwo');
    }

    private function protect(AllocationUnit $allocationUnit, string $startsAt, string $endsAt, ?string $expiresAt = null, AllocationUnit ...$additionalAllocationUnits): AllocationOccupancy
    {
        $occupancy = AllocationOccupancy::factory()->create([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'expires_at' => $expiresAt,
        ]);

        $occupancy->allocationUnits()->attach([
            $allocationUnit->id,
            ...array_map(fn (AllocationUnit $allocationUnit): int => $allocationUnit->id, $additionalAllocationUnits),
        ]);

        return $occupancy;
    }

    private function at(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, config('app.timezone'));
    }
}
