<?php

namespace Tests\Feature\Services;

use App\Actions\CreateAvailabilityBlock;
use App\Actions\CreateBookingRequest;
use App\Enums\AvailabilityReason;
use App\Enums\DayOfWeek;
use App\Exceptions\BookingSubmissionUnavailable;
use App\Models\AllocationUnit;
use App\Models\AvailabilityBlock;
use App\Models\Centre;
use App\Models\Equipment;
use App\Models\Facility;
use App\Models\FacilityBookableHour;
use App\Models\Resource;
use App\Models\ResourceBookableHour;
use App\Models\ResourceRate;
use App\Models\User;
use App\Services\AvailabilityResult;
use App\Services\AvailabilityService;
use App\Services\EquipmentRequirement;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ClosureAvailabilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemRoleSeeder::class);
        $this->travelTo($this->at('2026-10-01 12:00:00'));
    }

    #[TestWith(['centre'])]
    #[TestWith(['facility'])]
    #[TestWith(['resource'])]
    public function test_scope_blocks_only_the_authoritative_venue_or_physical_space(string $scope): void
    {
        $resource = $this->resource();
        $sameFacility = $this->resource($resource->facility);
        $otherFacility = $this->resource(Facility::factory()->for($resource->facility->centre)->create());
        $otherCentre = $this->resource();
        $manager = $this->manager($resource->facility->centre);
        $this->block($manager, $resource, $scope);

        $this->assertTrue($this->check($resource)->hasReason(AvailabilityReason::Blockout));
        $this->assertSame($scope !== 'resource', $this->check($sameFacility)->hasReason(AvailabilityReason::Blockout));
        $this->assertSame($scope === 'centre', $this->check($otherFacility)->hasReason(AvailabilityReason::Blockout));
        $this->assertTrue($this->check($otherCentre)->isAvailable());
    }

    #[TestWith(['2026-10-05 17:30:00', '2026-10-05 17:45:00', false])]
    #[TestWith(['2026-10-05 17:44:00', '2026-10-05 17:46:00', true])]
    #[TestWith(['2026-10-05 19:09:00', '2026-10-05 19:11:00', true])]
    #[TestWith(['2026-10-05 19:10:00', '2026-10-05 19:20:00', false])]
    #[TestWith(['2026-10-05 18:00:00', '2026-10-05 19:00:00', true])]
    public function test_closures_respect_setup_cleanup_and_half_open_boundaries(string $start, string $end, bool $blocked): void
    {
        $resource = $this->resource();
        $resource->update(['setup_minutes' => 15, 'cleanup_minutes' => 10]);
        $this->block($this->manager($resource->facility->centre), $resource, 'resource', $start, $end);

        $this->assertSame($blocked, $this->check($resource)->hasReason(AvailabilityReason::Blockout));
    }

    public function test_whole_resource_closure_blocks_both_children_but_not_isolated_space(): void
    {
        [$whole, $first, $second, $isolated] = $this->physicalResources();
        $this->block($this->manager($whole->facility->centre), $whole);

        $this->assertTrue($this->check($whole)->hasReason(AvailabilityReason::Blockout));
        $this->assertTrue($this->check($first)->hasReason(AvailabilityReason::Blockout));
        $this->assertTrue($this->check($second)->hasReason(AvailabilityReason::Blockout));
        $this->assertTrue($this->check($isolated)->isAvailable());
    }

    public function test_child_closure_blocks_whole_resource_but_not_disjoint_sibling(): void
    {
        [$whole, $first, $second, $isolated] = $this->physicalResources();
        $this->block($this->manager($whole->facility->centre), $first);

        $this->assertTrue($this->check($whole)->hasReason(AvailabilityReason::Blockout));
        $this->assertTrue($this->check($first)->hasReason(AvailabilityReason::Blockout));
        $this->assertTrue($this->check($second)->isAvailable());
        $this->assertTrue($this->check($isolated)->isAvailable());
    }

    public function test_effective_end_clamps_closure_without_rewriting_original_period(): void
    {
        $resource = $this->resource();
        $manager = $this->manager($resource->facility->centre);
        $block = $this->block($manager, $resource);
        $block->forceFill(['ended_at' => $this->at('2026-10-05 18:30:00'), 'ended_by' => $manager->id])->save();

        $this->assertTrue($this->check($resource, '2026-10-05 18:00:00', '2026-10-05 18:30:00')->hasReason(AvailabilityReason::Blockout));
        $this->assertTrue($this->check($resource, '2026-10-05 18:30:00', '2026-10-05 19:00:00')->isAvailable());
        $this->assertSame('2026-10-05 19:00:00', $block->fresh()->ends_at->toDateTimeString());
    }

    public function test_ending_before_start_removes_effective_block_and_ending_after_end_does_not_extend_it(): void
    {
        $resource = $this->resource();
        $manager = $this->manager($resource->facility->centre);
        $block = $this->block($manager, $resource);
        $block->forceFill(['ended_at' => $this->at('2026-10-05 17:00:00'), 'ended_by' => $manager->id])->save();
        $this->assertTrue($this->check($resource)->isAvailable());

        $block->forceFill(['ended_at' => $this->at('2026-10-05 20:00:00')])->save();
        $this->assertTrue($this->check($resource)->hasReason(AvailabilityReason::Blockout));
        $this->assertTrue($this->check($resource, '2026-10-05 19:00:00', '2026-10-05 20:00:00')->isAvailable());
    }

    public function test_ending_one_of_two_overlapping_closures_keeps_remaining_closure_effective(): void
    {
        $resource = $this->resource();
        $manager = $this->manager($resource->facility->centre);
        $first = $this->block($manager, $resource);
        $this->block($manager, $resource);
        $first->forceFill(['ended_at' => $this->at('2026-10-05 17:00:00'), 'ended_by' => $manager->id])->save();

        $this->assertTrue($this->check($resource)->hasReason(AvailabilityReason::Blockout));
    }

    public function test_setup_crossing_midnight_is_blocked_by_previous_day_closure(): void
    {
        $resource = $this->resource();
        $resource->update(['setup_minutes' => 15]);
        $this->block($this->manager($resource->facility->centre), $resource, 'resource', '2026-10-05 23:50:00', '2026-10-06 00:00:00');

        $result = $this->check($resource, '2026-10-06 00:05:00', '2026-10-06 01:00:00');
        $this->assertTrue($result->hasReason(AvailabilityReason::Blockout));
    }

    public function test_resource_closure_does_not_remove_equipment_from_unrelated_bookings(): void
    {
        $resource = $this->resource();
        $other = $this->resource($resource->facility);
        $equipment = Equipment::factory()->for($resource->facility->centre)->create(['quantity' => 4]);
        $this->block($this->manager($resource->facility->centre), $resource);

        $result = app(AvailabilityService::class)->check($other, $this->at('2026-10-05 18:00:00'), $this->at('2026-10-05 19:00:00'), [new EquipmentRequirement($equipment, 4)]);
        $this->assertTrue($result->isAvailable());
        $this->assertSame(4, $equipment->fresh()->quantity);
        $this->assertDatabaseCount('equipment_allocations', 0);
    }

    public function test_final_booking_creation_revalidates_closure_added_after_availability_check(): void
    {
        $resource = $this->resource();
        ResourceRate::factory()->for($resource)->create(['amount_minor' => 5000]);
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        $this->assertTrue($this->check($resource)->isAvailable());
        $this->block($this->manager($resource->facility->centre), $resource);

        try {
            app(CreateBookingRequest::class)->handle($customer, $resource->id, $this->at('2026-10-05 18:00:00'), $this->at('2026-10-05 19:00:00'));
            $this->fail('A request was persisted after a closure became authoritative.');
        } catch (BookingSubmissionUnavailable) {
            $this->assertDatabaseCount('bookings', 0);
            $this->assertDatabaseCount('allocation_occupancies', 0);
        }
    }

    public function test_recurring_submission_rechecks_a_closure_added_after_preview(): void
    {
        $resource = $this->resource();
        ResourceRate::factory()->for($resource)->create(['amount_minor' => 5000]);
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        $payload = ['resource_id' => $resource->id, 'starts_at' => '2026-10-05 18:00:00', 'ends_at' => '2026-10-05 19:00:00', 'interval_weeks' => 1, 'occurrence_count' => 3, 'timezone' => 'Europe/London', 'equipment' => []];
        $this->actingAs($customer)->postJson(route('bookings.recurring.preview'), $payload)->assertOk()->assertJsonPath('data.conflict_count', 0);
        $closureStart = CarbonImmutable::parse('2026-10-12 18:00:00', 'Europe/London')->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        $closureEnd = CarbonImmutable::parse('2026-10-12 19:00:00', 'Europe/London')->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        $this->block($this->manager($resource->facility->centre), $resource, 'resource', $closureStart, $closureEnd);

        $this->postJson(route('bookings.recurring.store'), [...$payload, 'submission_mode' => 'all_occurrences'])
            ->assertConflict()->assertJsonPath('data.conflict_count', 1)
            ->assertJsonPath('data.occurrences.1.status', 'conflict');
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_series', 0);
    }

    /** @return array{resource, resource, resource, resource} */
    private function physicalResources(): array
    {
        $whole = $this->resource();
        $first = $this->resource($whole->facility);
        $second = $this->resource($whole->facility);
        $isolated = $this->resource($whole->facility);
        $one = AllocationUnit::factory()->for($whole->facility)->create();
        $two = AllocationUnit::factory()->for($whole->facility)->create();
        $whole->syncAllocationUnits($one, $two);
        $first->syncAllocationUnits($one);
        $second->syncAllocationUnits($two);

        return [$whole, $first, $second, $isolated];
    }

    private function resource(?Facility $facility = null): Resource
    {
        $facility ??= Facility::factory()->create();
        if (! $facility->bookableHours()->exists()) {
            FacilityBookableHour::factory()->for($facility)->create(['day_of_week' => DayOfWeek::Monday, 'opens_at' => '08:00:00', 'closes_at' => '22:00:00']);
        }
        $resource = Resource::factory()->for($facility)->create();
        ResourceBookableHour::factory()->for($resource)->create(['day_of_week' => DayOfWeek::Monday, 'opens_at' => '08:00:00', 'closes_at' => '22:00:00']);
        $resource->syncAllocationUnits(AllocationUnit::factory()->for($facility)->create());

        return $resource;
    }

    private function block(User $manager, Resource $resource, string $scope = 'resource', string $start = '2026-10-05 18:00:00', string $end = '2026-10-05 19:00:00'): AvailabilityBlock
    {
        return app(CreateAvailabilityBlock::class)->handle($manager, ['centre_id' => $resource->facility->centre_id, 'scope' => $scope, 'facility_id' => $scope === 'centre' ? null : $resource->facility_id, 'resource_id' => $scope === 'resource' ? $resource->id : null, 'type' => 'maintenance', 'starts_at' => $start, 'ends_at' => $end, 'reason' => 'Planned maintenance']);
    }

    private function check(Resource $resource, string $start = '2026-10-05 18:00:00', string $end = '2026-10-05 19:00:00'): AvailabilityResult
    {
        return app(AvailabilityService::class)->check($resource, $this->at($start), $this->at($end));
    }

    private function at(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, config('app.timezone'));
    }

    private function manager(Centre $centre): User
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->assignedCentres()->attach($centre);

        return $manager;
    }
}
