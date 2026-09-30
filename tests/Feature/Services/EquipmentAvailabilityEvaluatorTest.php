<?php

namespace Tests\Feature\Services;

use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use App\Services\EquipmentAvailabilityEvaluator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EquipmentAvailabilityEvaluatorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private EquipmentAvailabilityEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluator = new EquipmentAvailabilityEvaluator;
    }

    public function test_equipment_with_no_allocations_is_available(): void
    {
        $equipment = $this->equipment(10);

        $this->assertTrue($this->available($equipment, 4));
    }

    public function test_requested_quantity_equal_to_the_configured_total_is_available(): void
    {
        $equipment = $this->equipment(10);

        $this->assertTrue($this->available($equipment, 10));
    }

    public function test_requested_quantity_greater_than_the_configured_total_is_unavailable(): void
    {
        $equipment = $this->equipment(10);

        $this->assertFalse($this->available($equipment, 11));
    }

    public function test_zero_and_negative_requested_quantities_are_unavailable(): void
    {
        $equipment = $this->equipment(10);

        $this->assertFalse($this->available($equipment, 0));
        $this->assertFalse($this->available($equipment, -1));
    }

    public function test_inactive_equipment_is_unavailable(): void
    {
        $equipment = $this->equipment(10, false);

        $this->assertFalse($this->available($equipment, 1));
    }

    public function test_overlapping_allocation_leaving_enough_quantity_is_available(): void
    {
        $equipment = $this->equipment(10);
        $this->allocate($equipment, 6);

        $this->assertTrue($this->available($equipment, 4));
    }

    public function test_overlapping_allocation_that_exhausts_quantity_is_unavailable(): void
    {
        $equipment = $this->equipment(10);
        $this->allocate($equipment, 7);

        $this->assertFalse($this->available($equipment, 4));
    }

    public function test_multiple_overlapping_allocations_are_summed(): void
    {
        $equipment = $this->equipment(10);
        $this->allocate($equipment, 3);
        $this->allocate($equipment, 2, '2026-10-15 18:15:00', '2026-10-15 19:15:00');

        $this->assertTrue($this->available($equipment, 5));
        $this->assertFalse($this->available($equipment, 6));
    }

    public function test_non_overlapping_allocations_do_not_count(): void
    {
        $equipment = $this->equipment(10);
        $this->allocate($equipment, 10, '2026-10-15 16:00:00', '2026-10-15 17:00:00');

        $this->assertTrue($this->available($equipment, 10));
    }

    public function test_allocations_for_a_different_equipment_record_do_not_count(): void
    {
        $equipment = $this->equipment(10);
        $this->allocate($this->equipment(10), 10);

        $this->assertTrue($this->available($equipment, 10));
    }

    public function test_adjacent_allocation_boundaries_do_not_overlap(): void
    {
        $equipment = $this->equipment(10);
        $this->allocate($equipment, 10);

        $this->assertTrue($this->available($equipment, 10, '2026-10-15 17:00:00', '2026-10-15 18:00:00'));
        $this->assertTrue($this->available($equipment, 10, '2026-10-15 19:00:00', '2026-10-15 20:00:00'));
    }

    public function test_exact_same_range_counts_as_an_overlap(): void
    {
        $equipment = $this->equipment(10);
        $this->allocate($equipment, 7);

        $this->assertFalse($this->available($equipment, 4));
    }

    public function test_contained_and_containing_ranges_count_as_overlaps(): void
    {
        $equipment = $this->equipment(10);
        $this->allocate($equipment, 7);

        $this->assertFalse($this->available($equipment, 4, '2026-10-15 18:15:00', '2026-10-15 18:45:00'));
        $this->assertFalse($this->available($equipment, 4, '2026-10-15 17:30:00', '2026-10-15 19:30:00'));
    }

    public function test_cross_midnight_overlaps_count(): void
    {
        $equipment = $this->equipment(10);
        $this->allocate($equipment, 7, '2026-10-15 23:30:00', '2026-10-16 00:30:00');

        $this->assertFalse($this->available($equipment, 4, '2026-10-15 23:45:00', '2026-10-16 00:15:00'));
    }

    public function test_non_expiring_allocation_counts(): void
    {
        $equipment = $this->equipment(10);
        $this->allocate($equipment, 7);

        $this->assertFalse($this->available($equipment, 4));
    }

    public function test_unexpired_temporary_allocation_counts(): void
    {
        $equipment = $this->equipment(10);
        $this->allocate($equipment, 7, expiresAt: '2026-10-10 13:00:00');

        $this->assertFalse($this->available($equipment, 4));
    }

    public function test_expired_temporary_allocation_does_not_count(): void
    {
        $equipment = $this->equipment(10);
        $this->allocate($equipment, 10, expiresAt: '2026-10-10 11:59:00');

        $this->assertTrue($this->available($equipment, 10));
    }

    public function test_temporary_allocation_expiring_at_the_evaluation_instant_does_not_count(): void
    {
        $equipment = $this->equipment(10);
        $this->allocate($equipment, 10, expiresAt: '2026-10-10 12:00:00');

        $this->assertTrue($this->available($equipment, 10));
    }

    public function test_zero_duration_and_reversed_requests_are_unavailable(): void
    {
        $equipment = $this->equipment(10);

        $this->assertFalse($this->available($equipment, 1, '2026-10-15 18:00:00', '2026-10-15 18:00:00'));
        $this->assertFalse($this->available($equipment, 1, '2026-10-15 19:00:00', '2026-10-15 18:00:00'));
    }

    public function test_overlapping_quantity_is_calculated_with_one_aggregate_query(): void
    {
        $equipment = $this->equipment(10);
        $this->allocate($equipment, 4);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $isAvailable = $this->available($equipment, 6);

        $this->assertTrue($isAvailable);
        $this->assertCount(1, DB::getQueryLog());
    }

    private function equipment(int $quantity, bool $isActive = true): Equipment
    {
        return Equipment::factory()->create([
            'quantity' => $quantity,
            'is_active' => $isActive,
        ]);
    }

    private function allocate(
        Equipment $equipment,
        int $quantity,
        string $startsAt = '2026-10-15 18:00:00',
        string $endsAt = '2026-10-15 19:00:00',
        ?string $expiresAt = null,
    ): EquipmentAllocation {
        return EquipmentAllocation::factory()->for($equipment)->create([
            'quantity' => $quantity,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'expires_at' => $expiresAt,
        ]);
    }

    private function available(
        Equipment $equipment,
        int $quantity,
        string $startsAt = '2026-10-15 18:00:00',
        string $endsAt = '2026-10-15 19:00:00',
    ): bool {
        return $this->evaluator->isAvailable(
            $equipment,
            $quantity,
            $this->at($startsAt),
            $this->at($endsAt),
            $this->at('2026-10-10 12:00:00'),
        );
    }

    private function at(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, config('app.timezone'));
    }
}
