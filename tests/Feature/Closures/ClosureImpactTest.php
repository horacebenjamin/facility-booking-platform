<?php

namespace Tests\Feature\Closures;

use App\Actions\CreateAvailabilityBlock;
use App\Actions\DetectClosureAffectedBookings;
use App\Enums\AttendanceState;
use App\Enums\BookingStatus;
use App\Enums\ClosureImpactStatus;
use App\Enums\FinancialStatus;
use App\Models\AllocationOccupancy;
use App\Models\AllocationUnit;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\BookingSeries;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ClosureImpactTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemRoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-05 09:00:00', config('app.timezone')));
    }

    public function test_only_protected_or_confirmed_occurrences_are_identified_without_mutating_bookings(): void
    {
        $resource = Resource::factory()->create();
        $manager = $this->manager($resource);
        $confirmed = $this->booking($resource, ['status' => BookingStatus::Confirmed, 'financial_status' => FinancialStatus::Paid, 'attendance_state' => AttendanceState::NoShow, 'no_show_recorded_at' => '2026-10-05 08:00:00']);
        $approved = $this->booking($resource, ['status' => BookingStatus::Approved, 'financial_status' => FinancialStatus::AwaitingPayment]);
        $active = $this->booking($resource);
        AllocationOccupancy::factory()->create(['booking_id' => $active->id, 'starts_at' => $active->starts_at, 'ends_at' => $active->ends_at, 'expires_at' => '2026-10-05 09:01:00']);
        $expired = $this->booking($resource);
        AllocationOccupancy::factory()->create(['booking_id' => $expired->id, 'expires_at' => '2026-10-05 09:00:00']);
        $this->booking($resource);
        $this->booking($resource, ['status' => BookingStatus::Rejected]);
        $this->booking(Resource::factory()->create(), ['status' => BookingStatus::Confirmed]);
        $before = Booking::query()->get()->mapWithKeys(fn (Booking $booking) => [$booking->id => $booking->getAttributes()])->all();

        $block = $this->create($manager, $resource);

        $this->assertEqualsCanonicalizing([$confirmed->id, $approved->id, $active->id], $block->impacts()->pluck('booking_id')->all());
        $this->assertSame($before, Booking::query()->get()->mapWithKeys(fn (Booking $booking) => [$booking->id => $booking->getAttributes()])->all());
        $this->assertDatabaseCount('allocation_occupancies', 2);
        $this->assertSame(3, Activity::query()->where('event', 'closure.booking_affected')->count());
        $this->assertSame($manager->id, Activity::query()->where('event', 'closure.created')->sole()->causer_id);
    }

    public function test_physical_sharing_and_operational_buffers_use_half_open_overlap(): void
    {
        $whole = Resource::factory()->create();
        $court = Resource::factory()->create(['facility_id' => $whole->facility_id, 'setup_minutes' => 15, 'cleanup_minutes' => 10]);
        $unit = AllocationUnit::factory()->create(['facility_id' => $whole->facility_id]);
        $whole->allocationUnits()->attach($unit, ['facility_id' => $whole->facility_id]);
        $court->allocationUnits()->attach($unit, ['facility_id' => $whole->facility_id]);
        $setup = $this->booking($court, ['status' => BookingStatus::Confirmed, 'starts_at' => '2026-10-05 12:10:00', 'ends_at' => '2026-10-05 13:00:00']);
        $cleanup = $this->booking($court, ['status' => BookingStatus::Confirmed, 'starts_at' => '2026-10-05 09:00:00', 'ends_at' => '2026-10-05 09:55:00']);
        $this->booking($court, ['status' => BookingStatus::Confirmed, 'starts_at' => '2026-10-05 08:00:00', 'ends_at' => '2026-10-05 09:50:00']);
        $this->booking($court, ['status' => BookingStatus::Confirmed, 'starts_at' => '2026-10-05 12:15:00', 'ends_at' => '2026-10-05 13:00:00']);

        $block = $this->create($this->manager($whole), $whole);

        $this->assertEqualsCanonicalizing([$setup->id, $cleanup->id], $block->impacts()->pluck('booking_id')->all());
    }

    public function test_recurring_occurrences_are_independent_and_redetection_preserves_resolution_and_audit(): void
    {
        $resource = Resource::factory()->create();
        $manager = $this->manager($resource);
        $series = BookingSeries::factory()->create(['resource_id' => $resource->id]);
        $affected = $this->booking($resource, ['status' => BookingStatus::Confirmed, 'customer_id' => $series->customer_id, 'booking_series_id' => $series->id, 'occurrence_index' => 1]);
        $this->booking($resource, ['status' => BookingStatus::Confirmed, 'customer_id' => $series->customer_id, 'booking_series_id' => $series->id, 'occurrence_index' => 2, 'starts_at' => '2026-10-12 10:00:00', 'ends_at' => '2026-10-12 11:00:00']);
        $block = $this->create($manager, $resource);
        $impact = $block->impacts()->sole();
        $this->assertSame($affected->id, $impact->booking_id);
        $impact->forceFill(['status' => ClosureImpactStatus::Resolved, 'resolved_at' => now(), 'resolved_by' => $manager->id])->save();
        $auditCount = Activity::query()->count();

        $this->assertSame(0, app(DetectClosureAffectedBookings::class)->handle($manager, $block));

        $this->assertDatabaseCount('availability_block_booking_impacts', 1);
        $this->assertSame(ClosureImpactStatus::Resolved, $impact->fresh()->status);
        $this->assertSame($auditCount, Activity::query()->count());
        $block->forceFill(['ended_at' => now(), 'ended_by' => $manager->id])->save();
        $this->booking($resource, ['status' => BookingStatus::Confirmed]);
        $this->assertSame(0, app(DetectClosureAffectedBookings::class)->handle($manager, $block));
        $this->assertDatabaseCount('availability_block_booking_impacts', 1);
    }

    public function test_persisted_buffers_are_authoritative_after_resource_settings_change(): void
    {
        $resource = Resource::factory()->create(['setup_minutes' => 0, 'cleanup_minutes' => 0]);
        $setup = $this->booking($resource, ['status' => BookingStatus::Confirmed, 'starts_at' => '2026-10-05 12:10:00', 'ends_at' => '2026-10-05 13:00:00']);
        AllocationOccupancy::factory()->create(['booking_id' => $setup->id, 'starts_at' => '2026-10-05 11:55:00', 'ends_at' => '2026-10-05 13:00:00', 'expires_at' => null]);
        $cleanup = $this->booking($resource, ['status' => BookingStatus::Confirmed, 'starts_at' => '2026-10-05 09:00:00', 'ends_at' => '2026-10-05 09:55:00']);
        AllocationOccupancy::factory()->create(['booking_id' => $cleanup->id, 'starts_at' => '2026-10-05 09:00:00', 'ends_at' => '2026-10-05 10:05:00', 'expires_at' => null]);
        $unaffected = $this->booking($resource, ['status' => BookingStatus::Confirmed, 'starts_at' => '2026-10-05 13:00:00', 'ends_at' => '2026-10-05 14:00:00']);
        AllocationOccupancy::factory()->create(['booking_id' => $unaffected->id, 'starts_at' => '2026-10-05 13:00:00', 'ends_at' => '2026-10-05 14:00:00', 'expires_at' => null]);
        $resource->update(['setup_minutes' => 120]);

        $block = $this->create($this->manager($resource), $resource);

        $this->assertEqualsCanonicalizing([$setup->id, $cleanup->id], $block->impacts()->pluck('booking_id')->all());
    }

    public function test_persisted_physical_units_remain_authoritative_after_resource_remapping(): void
    {
        $closed = Resource::factory()->create();
        $booked = Resource::factory()->for($closed->facility)->create();
        $unit = AllocationUnit::factory()->for($closed->facility)->create();
        $replacement = AllocationUnit::factory()->for($closed->facility)->create();
        $closed->syncAllocationUnits($unit);
        $booked->syncAllocationUnits($replacement);
        $affected = $this->booking($booked, ['status' => BookingStatus::Confirmed]);
        $reservation = AllocationOccupancy::factory()->create(['booking_id' => $affected->id, 'starts_at' => $affected->starts_at, 'ends_at' => $affected->ends_at, 'expires_at' => null]);
        $reservation->allocationUnits()->attach($unit);
        $unaffected = $this->booking($booked, ['status' => BookingStatus::Confirmed]);
        $other = AllocationOccupancy::factory()->create(['booking_id' => $unaffected->id, 'starts_at' => $unaffected->starts_at, 'ends_at' => $unaffected->ends_at, 'expires_at' => null]);
        $other->allocationUnits()->attach($replacement);

        $block = $this->create($this->manager($closed), $closed);

        $this->assertSame([$affected->id], $block->impacts()->pluck('booking_id')->all());
    }

    private function manager(Resource $resource): User
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->assignedCentres()->attach($resource->facility->centre_id);

        return $manager;
    }

    /** @param array<string, mixed> $attributes */
    private function booking(Resource $resource, array $attributes = []): Booking
    {
        return Booking::factory()->create(['resource_id' => $resource->id, 'starts_at' => '2026-10-05 10:00:00', 'ends_at' => '2026-10-05 11:00:00', ...$attributes]);
    }

    private function create(User $manager, Resource $resource): AvailabilityBlock
    {
        return app(CreateAvailabilityBlock::class)->handle($manager, ['centre_id' => $resource->facility->centre_id, 'facility_id' => $resource->facility_id, 'resource_id' => $resource->id, 'scope' => 'resource', 'type' => 'maintenance', 'reason' => 'Repair floor', 'starts_at' => '2026-10-05 10:00:00', 'ends_at' => '2026-10-05 12:00:00']);
    }
}
