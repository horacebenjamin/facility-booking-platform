<?php

namespace Tests\Feature\Services;

use App\Models\AllocationOccupancy;
use App\Models\AllocationUnit;
use App\Models\Resource;
use App\Services\AllocationConflictEvaluator;
use App\Services\OperationalOccupancyCalculator;
use App\Services\OperationalOccupancyPeriod;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OperationalOccupancyCalculatorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private OperationalOccupancyCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new OperationalOccupancyCalculator;
    }

    public function test_zero_setup_and_cleanup_returns_the_customer_visible_period(): void
    {
        $resource = Resource::factory()->make(['setup_minutes' => 0, 'cleanup_minutes' => 0]);

        $period = $this->calculator->calculate($resource, $this->at('2026-10-15 18:00:00'), $this->at('2026-10-15 19:00:00'));

        $this->assertInstanceOf(OperationalOccupancyPeriod::class, $period);
        $this->assertSame('2026-10-15 18:00:00', $period->startsAt->toDateTimeString());
        $this->assertSame('2026-10-15 19:00:00', $period->endsAt->toDateTimeString());
    }

    public function test_setup_expands_the_operational_start_backwards(): void
    {
        $resource = Resource::factory()->make(['setup_minutes' => 30, 'cleanup_minutes' => 0]);

        $period = $this->calculator->calculate($resource, $this->at('2026-10-15 18:00:00'), $this->at('2026-10-15 19:00:00'));

        $this->assertInstanceOf(OperationalOccupancyPeriod::class, $period);
        $this->assertSame('2026-10-15 17:30:00', $period->startsAt->toDateTimeString());
        $this->assertSame('2026-10-15 19:00:00', $period->endsAt->toDateTimeString());
    }

    public function test_cleanup_expands_the_operational_end_forwards(): void
    {
        $resource = Resource::factory()->make(['setup_minutes' => 0, 'cleanup_minutes' => 20]);

        $period = $this->calculator->calculate($resource, $this->at('2026-10-15 18:00:00'), $this->at('2026-10-15 19:00:00'));

        $this->assertInstanceOf(OperationalOccupancyPeriod::class, $period);
        $this->assertSame('2026-10-15 18:00:00', $period->startsAt->toDateTimeString());
        $this->assertSame('2026-10-15 19:20:00', $period->endsAt->toDateTimeString());
    }

    public function test_setup_and_cleanup_expand_both_operational_boundaries(): void
    {
        $resource = Resource::factory()->make(['setup_minutes' => 15, 'cleanup_minutes' => 10]);

        $period = $this->calculator->calculate($resource, $this->at('2026-10-15 18:00:00'), $this->at('2026-10-15 19:00:00'));

        $this->assertInstanceOf(OperationalOccupancyPeriod::class, $period);
        $this->assertSame('2026-10-15 17:45:00', $period->startsAt->toDateTimeString());
        $this->assertSame('2026-10-15 19:10:00', $period->endsAt->toDateTimeString());
    }

    public function test_large_setup_expands_the_operational_start_correctly(): void
    {
        $resource = Resource::factory()->make(['setup_minutes' => 180, 'cleanup_minutes' => 0]);

        $period = $this->calculator->calculate($resource, $this->at('2026-10-15 18:00:00'), $this->at('2026-10-15 19:00:00'));

        $this->assertInstanceOf(OperationalOccupancyPeriod::class, $period);
        $this->assertSame('2026-10-15 15:00:00', $period->startsAt->toDateTimeString());
    }

    public function test_large_cleanup_expands_the_operational_end_correctly(): void
    {
        $resource = Resource::factory()->make(['setup_minutes' => 0, 'cleanup_minutes' => 150]);

        $period = $this->calculator->calculate($resource, $this->at('2026-10-15 18:00:00'), $this->at('2026-10-15 19:00:00'));

        $this->assertInstanceOf(OperationalOccupancyPeriod::class, $period);
        $this->assertSame('2026-10-15 21:30:00', $period->endsAt->toDateTimeString());
    }

    public function test_cross_midnight_request_remains_valid(): void
    {
        $resource = Resource::factory()->make(['setup_minutes' => 0, 'cleanup_minutes' => 0]);

        $period = $this->calculator->calculate($resource, $this->at('2026-10-15 23:30:00'), $this->at('2026-10-16 00:30:00'));

        $this->assertInstanceOf(OperationalOccupancyPeriod::class, $period);
        $this->assertSame('2026-10-15 23:30:00', $period->startsAt->toDateTimeString());
        $this->assertSame('2026-10-16 00:30:00', $period->endsAt->toDateTimeString());
    }

    public function test_setup_can_expand_the_operational_start_into_the_previous_day(): void
    {
        $resource = Resource::factory()->make(['setup_minutes' => 20, 'cleanup_minutes' => 0]);

        $period = $this->calculator->calculate($resource, $this->at('2026-10-15 00:10:00'), $this->at('2026-10-15 01:00:00'));

        $this->assertInstanceOf(OperationalOccupancyPeriod::class, $period);
        $this->assertSame('2026-10-14 23:50:00', $period->startsAt->toDateTimeString());
    }

    public function test_cleanup_can_expand_the_operational_end_into_the_next_day(): void
    {
        $resource = Resource::factory()->make(['setup_minutes' => 0, 'cleanup_minutes' => 20]);

        $period = $this->calculator->calculate($resource, $this->at('2026-10-15 23:00:00'), $this->at('2026-10-15 23:50:00'));

        $this->assertInstanceOf(OperationalOccupancyPeriod::class, $period);
        $this->assertSame('2026-10-16 00:10:00', $period->endsAt->toDateTimeString());
    }

    public function test_zero_duration_request_returns_null(): void
    {
        $resource = Resource::factory()->make(['setup_minutes' => 15, 'cleanup_minutes' => 10]);

        $period = $this->calculator->calculate($resource, $this->at('2026-10-15 18:00:00'), $this->at('2026-10-15 18:00:00'));

        $this->assertNull($period);
    }

    public function test_reversed_request_returns_null(): void
    {
        $resource = Resource::factory()->make(['setup_minutes' => 15, 'cleanup_minutes' => 10]);

        $period = $this->calculator->calculate($resource, $this->at('2026-10-15 19:00:00'), $this->at('2026-10-15 18:00:00'));

        $this->assertNull($period);
    }

    public function test_original_carbon_instances_are_not_mutated(): void
    {
        $resource = Resource::factory()->make(['setup_minutes' => 15, 'cleanup_minutes' => 10]);
        $startsAt = Carbon::parse('2026-10-15 18:00:00', config('app.timezone'));
        $endsAt = Carbon::parse('2026-10-15 19:00:00', config('app.timezone'));

        $period = $this->calculator->calculate($resource, $startsAt, $endsAt);

        $this->assertInstanceOf(OperationalOccupancyPeriod::class, $period);
        $this->assertSame('2026-10-15 18:00:00', $startsAt->toDateTimeString());
        $this->assertSame('2026-10-15 19:00:00', $endsAt->toDateTimeString());
    }

    public function test_returned_values_are_normalised_to_the_application_timezone(): void
    {
        $resource = Resource::factory()->make(['setup_minutes' => 0, 'cleanup_minutes' => 0]);
        $startsAt = CarbonImmutable::parse('2026-10-15 19:00:00', 'Europe/London');
        $endsAt = CarbonImmutable::parse('2026-10-15 20:00:00', 'Europe/London');

        $period = $this->calculator->calculate($resource, $startsAt, $endsAt);

        $this->assertInstanceOf(OperationalOccupancyPeriod::class, $period);
        $this->assertSame(config('app.timezone'), $period->startsAt->getTimezone()->getName());
        $this->assertSame(config('app.timezone'), $period->endsAt->getTimezone()->getName());
        $this->assertSame('2026-10-15 18:00:00', $period->startsAt->toDateTimeString());
        $this->assertSame('2026-10-15 19:00:00', $period->endsAt->toDateTimeString());
    }

    public function test_exact_minute_arithmetic_preserves_seconds(): void
    {
        $resource = Resource::factory()->make(['setup_minutes' => 15, 'cleanup_minutes' => 10]);

        $period = $this->calculator->calculate($resource, $this->at('2026-10-15 18:00:37'), $this->at('2026-10-15 19:00:37'));

        $this->assertInstanceOf(OperationalOccupancyPeriod::class, $period);
        $this->assertSame('2026-10-15 17:45:37', $period->startsAt->toDateTimeString());
        $this->assertSame('2026-10-15 19:10:37', $period->endsAt->toDateTimeString());
    }

    public function test_operational_setup_period_conflicts_with_an_existing_occupancy(): void
    {
        $resource = Resource::factory()->create(['setup_minutes' => 15, 'cleanup_minutes' => 0]);
        $allocationUnit = AllocationUnit::factory()->for($resource->facility)->create();
        $resource->syncAllocationUnits($allocationUnit);
        $this->protect($allocationUnit, '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $customerVisibleConflict = (new AllocationConflictEvaluator)->hasConflict(
            $resource,
            $this->at('2026-10-15 19:10:00'),
            $this->at('2026-10-15 20:00:00'),
        );
        $operationalPeriod = $this->calculator->calculate($resource, $this->at('2026-10-15 19:10:00'), $this->at('2026-10-15 20:00:00'));

        $this->assertFalse($customerVisibleConflict);
        $this->assertInstanceOf(OperationalOccupancyPeriod::class, $operationalPeriod);
        $this->assertTrue((new AllocationConflictEvaluator)->hasConflict($resource, $operationalPeriod->startsAt, $operationalPeriod->endsAt));
    }

    public function test_operational_setup_period_adjacent_to_an_existing_occupancy_does_not_conflict(): void
    {
        $resource = Resource::factory()->create(['setup_minutes' => 15, 'cleanup_minutes' => 0]);
        $allocationUnit = AllocationUnit::factory()->for($resource->facility)->create();
        $resource->syncAllocationUnits($allocationUnit);
        $this->protect($allocationUnit, '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $operationalPeriod = $this->calculator->calculate($resource, $this->at('2026-10-15 19:15:00'), $this->at('2026-10-15 20:00:00'));

        $this->assertInstanceOf(OperationalOccupancyPeriod::class, $operationalPeriod);
        $this->assertSame('2026-10-15 19:00:00', $operationalPeriod->startsAt->toDateTimeString());
        $this->assertFalse((new AllocationConflictEvaluator)->hasConflict($resource, $operationalPeriod->startsAt, $operationalPeriod->endsAt));
    }

    private function protect(AllocationUnit $allocationUnit, string $startsAt, string $endsAt): AllocationOccupancy
    {
        $occupancy = AllocationOccupancy::factory()->create([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $occupancy->allocationUnits()->attach($allocationUnit);

        return $occupancy;
    }

    private function at(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, config('app.timezone'));
    }
}
