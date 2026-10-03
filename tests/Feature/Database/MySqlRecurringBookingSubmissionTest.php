<?php

namespace Tests\Feature\Database;

use App\Actions\ApproveBooking;
use App\Actions\CreateRecurringBookingRequest;
use App\Enums\AvailabilityReason;
use App\Enums\BookingStatus;
use App\Enums\DayOfWeek;
use App\Enums\FinancialStatus;
use App\Enums\PricingFailureReason;
use App\Enums\RecurrenceFrequency;
use App\Enums\RecurringBookingConflictReason;
use App\Models\AllocationOccupancy;
use App\Models\AllocationUnit;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\BookingPriceSnapshot;
use App\Models\Centre;
use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use App\Models\EquipmentRate;
use App\Models\Facility;
use App\Models\FacilityBookableHour;
use App\Models\Resource;
use App\Models\ResourceBookableHour;
use App\Models\ResourceRate;
use App\Models\User;
use App\Services\CreateBookingSeriesResult;
use App\Services\RecurrencePattern;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class MySqlRecurringBookingSubmissionTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->seed(SystemRoleSeeder::class);
        config(['booking.provisional_hold_hours' => 48]);
        CarbonImmutable::setTestNow('2026-10-01 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_all_valid_occurrences_create_one_series_with_independent_bookings_prices_and_protection(): void
    {
        $fixture = $this->bookableFixture(withEquipment: true);
        $customer = $this->customer();

        $result = $this->createSeries($customer, $fixture['resource'], 3, [
            ['equipment_id' => $fixture['equipment']->id, 'quantity' => 2],
        ]);

        $this->assertTrue($result->wasCreated());
        $this->assertNotNull($result->series);
        $this->assertCount(3, $result->bookings);
        $this->assertSame(3, $result->series->bookings()->count());
        $this->assertSame([1, 2, 3], $result->series->bookings()->pluck('occurrence_index')->all());
        $this->assertCount(3, $result->series->bookings()->pluck('reference')->unique());
        $this->assertDatabaseCount('booking_price_snapshots', 3);
        $this->assertDatabaseCount('allocation_occupancies', 3);
        $this->assertDatabaseCount('allocation_occupancy_allocation_unit', 6);
        $this->assertDatabaseCount('booking_equipment', 3);
        $this->assertDatabaseCount('equipment_allocations', 3);
        $this->assertSame(3, Booking::query()->whereNotNull('booking_series_id')->count());
        $this->assertTrue(Booking::query()->get()->every(
            static fn (Booking $booking): bool => $booking->priceSnapshot !== null
                && $booking->allocationOccupancy !== null
                && $booking->equipmentAllocations()->count() === 1,
        ));
    }

    public function test_every_occurrence_is_validated_and_all_conflicts_are_returned_without_silent_omission(): void
    {
        $fixture = $this->bookableFixture();
        $customer = $this->customer();

        foreach (['2026-10-12', '2026-10-19'] as $date) {
            AvailabilityBlock::factory()->forResource($fixture['resource'])->create([
                'starts_at' => "{$date} 17:00:00",
                'ends_at' => "{$date} 18:30:00",
            ]);
        }

        $result = $this->createSeries($customer, $fixture['resource'], 3);

        $this->assertFalse($result->wasCreated());
        $this->assertSame([1], array_map(
            static fn ($occurrence): int => $occurrence->period->index,
            $result->validation->validOccurrences,
        ));
        $this->assertSame([2, 3], array_map(
            static fn ($conflict): int => $conflict->period->index,
            $result->validation->conflicts,
        ));
        $this->assertSame(3, count($result->validation->validOccurrences) + count($result->validation->conflicts));
        $this->assertTrue(collect($result->validation->conflicts)->every(
            static fn ($conflict): bool => in_array(RecurringBookingConflictReason::Availability, $conflict->reasons, true)
                && in_array(AvailabilityReason::Blockout, $conflict->availabilityReasons, true),
        ));
        $this->assertDatabaseEmpty('booking_series');
        $this->assertDatabaseEmpty('bookings');
    }

    public function test_every_available_occurrence_is_priced_using_its_own_effective_rate(): void
    {
        $fixture = $this->bookableFixture(withRate: false);
        $customer = $this->customer();

        foreach ([
            ['2026-10-05', 1000],
            ['2026-10-12', 2000],
            ['2026-10-19', 3000],
        ] as [$date, $amount]) {
            ResourceRate::factory()->for($fixture['resource'])->create([
                'amount_minor' => $amount,
                'effective_from' => $date,
                'effective_until' => $date,
            ]);
        }

        $result = $this->createSeries($customer, $fixture['resource'], 3);

        $this->assertTrue($result->wasCreated());
        $this->assertSame([1500, 3000, 4500], BookingPriceSnapshot::query()
            ->join('bookings', 'bookings.id', '=', 'booking_price_snapshots.booking_id')
            ->orderBy('bookings.occurrence_index')
            ->pluck('booking_price_snapshots.final_total_minor')
            ->all());
    }

    public function test_an_explicit_valid_subset_is_persisted_atomically_with_original_occurrence_identity(): void
    {
        $fixture = $this->bookableFixture();
        AvailabilityBlock::factory()->forResource($fixture['resource'])->create([
            'starts_at' => '2026-10-12 17:00:00',
            'ends_at' => '2026-10-12 18:30:00',
        ]);

        $result = $this->createSeries(
            $this->customer(),
            $fixture['resource'],
            3,
            selectedOccurrenceIndexes: [1, 3],
        );

        $this->assertTrue($result->wasCreated());
        $this->assertSame(3, $result->series->occurrence_count);
        $this->assertSame([1, 3], $result->series->bookings()->pluck('occurrence_index')->all());
        $this->assertCount(2, $result->bookings);
        $this->assertDatabaseCount('booking_price_snapshots', 2);
        $this->assertDatabaseCount('allocation_occupancies', 2);
    }

    public function test_an_explicit_subset_containing_a_conflict_creates_nothing(): void
    {
        $fixture = $this->bookableFixture();
        AvailabilityBlock::factory()->forResource($fixture['resource'])->create([
            'starts_at' => '2026-10-12 17:00:00',
            'ends_at' => '2026-10-12 18:30:00',
        ]);

        $result = $this->createSeries(
            $this->customer(),
            $fixture['resource'],
            3,
            selectedOccurrenceIndexes: [1, 2, 3],
        );

        $this->assertFalse($result->wasCreated());
        $this->assertSame([2], array_map(
            static fn ($conflict): int => $conflict->period->index,
            $result->validation->conflicts,
        ));
        $this->assertDatabaseEmpty('booking_series');
        $this->assertDatabaseEmpty('bookings');
    }

    public function test_pricing_failure_identifies_the_occurrence_and_keeps_the_series_atomic(): void
    {
        $fixture = $this->bookableFixture(withRate: false);
        $customer = $this->customer();

        foreach (['2026-10-05', '2026-10-12'] as $date) {
            ResourceRate::factory()->for($fixture['resource'])->create([
                'effective_from' => $date,
                'effective_until' => $date,
            ]);
        }

        $result = $this->createSeries($customer, $fixture['resource'], 3);

        $this->assertFalse($result->wasCreated());
        $this->assertSame([1, 2], array_map(
            static fn ($occurrence): int => $occurrence->period->index,
            $result->validation->validOccurrences,
        ));
        $this->assertSame(3, $result->validation->conflicts[0]->period->index);
        $this->assertSame(PricingFailureReason::MissingResourceRate, $result->validation->conflicts[0]->pricingFailure?->reason);
        $this->assertDatabaseEmpty('booking_series');
        $this->assertDatabaseEmpty('bookings');
    }

    public function test_equipment_exhaustion_is_reported_for_the_affected_occurrence(): void
    {
        $fixture = $this->bookableFixture(withEquipment: true);
        $customer = $this->customer();
        EquipmentAllocation::factory()->for($fixture['equipment'])->create([
            'quantity' => 4,
            'starts_at' => '2026-10-12 17:00:00',
            'ends_at' => '2026-10-12 18:30:00',
        ]);

        $result = $this->createSeries($customer, $fixture['resource'], 3, [
            ['equipment_id' => $fixture['equipment']->id, 'quantity' => 1],
        ]);

        $this->assertSame([1, 3], array_map(
            static fn ($occurrence): int => $occurrence->period->index,
            $result->validation->validOccurrences,
        ));
        $this->assertSame(2, $result->validation->conflicts[0]->period->index);
        $this->assertContains(AvailabilityReason::EquipmentUnavailable, $result->validation->conflicts[0]->availabilityReasons);
        $this->assertDatabaseEmpty('booking_series');
        $this->assertDatabaseEmpty('bookings');
    }

    public function test_parent_child_resource_conflict_is_reported_per_occurrence(): void
    {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->for($centre)->create();
        $sharedUnit = AllocationUnit::factory()->for($facility)->create(['code' => 'shared']);
        $wholeHall = $this->createBookableResource($facility, 'Whole Hall');
        $court = $this->createBookableResource($facility, 'Court A');
        $wholeHall->syncAllocationUnits($sharedUnit);
        $court->syncAllocationUnits($sharedUnit);
        $existing = AllocationOccupancy::factory()->create([
            'starts_at' => '2026-10-12 17:00:00',
            'ends_at' => '2026-10-12 18:30:00',
        ]);
        $existing->allocationUnits()->attach($sharedUnit);

        $result = $this->createSeries($this->customer(), $court, 3);

        $this->assertSame(2, $result->validation->conflicts[0]->period->index);
        $this->assertContains(AvailabilityReason::ResourceConflict, $result->validation->conflicts[0]->availabilityReasons);
        $this->assertDatabaseEmpty('booking_series');
        $this->assertDatabaseEmpty('bookings');
    }

    public function test_failure_during_occurrence_persistence_rolls_back_the_series_and_every_booking(): void
    {
        $fixture = $this->bookableFixture();
        $createdBookings = 0;
        $failPersistence = true;
        Booking::creating(function () use (&$createdBookings, &$failPersistence): void {
            $createdBookings++;

            if ($failPersistence && $createdBookings === 2) {
                throw new RuntimeException('Simulated occurrence persistence failure.');
            }
        });

        try {
            $this->createSeries($this->customer(), $fixture['resource'], 3);
            $this->fail('The simulated persistence failure should escape the transaction.');
        } catch (RuntimeException $exception) {
            $failPersistence = false;
            $this->assertSame('Simulated occurrence persistence failure.', $exception->getMessage());
        }

        $this->assertDatabaseEmpty('booking_series');
        $this->assertDatabaseEmpty('bookings');
        $this->assertDatabaseEmpty('booking_price_snapshots');
        $this->assertDatabaseEmpty('allocation_occupancies');
        $this->assertDatabaseEmpty('activity_log');
    }

    public function test_recurring_creation_requires_booking_creation_permission(): void
    {
        $fixture = $this->bookableFixture();
        $unauthorisedUser = User::factory()->create();

        try {
            $this->createSeries($unauthorisedUser, $fixture['resource'], 2);
            $this->fail('A user without booking creation permission must not create a series.');
        } catch (AuthorizationException) {
            $this->assertDatabaseEmpty('booking_series');
            $this->assertDatabaseEmpty('bookings');
        }
    }

    public function test_management_review_can_approve_one_recurring_occurrence_independently(): void
    {
        $fixture = $this->bookableFixture();
        $result = $this->createSeries($this->customer(), $fixture['resource'], 2);
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->assignedCentres()->attach($fixture['resource']->facility->centre);

        $approved = app(ApproveBooking::class)->handle($manager, $result->bookings[0]);
        $unchangedOccurrence = $result->bookings[1]->fresh();

        $this->assertSame(BookingStatus::Approved, $approved->status);
        $this->assertSame(FinancialStatus::AwaitingPayment, $approved->financial_status);
        $this->assertNull($approved->allocationOccupancy->expires_at);
        $this->assertSame(BookingStatus::Requested, $unchangedOccurrence->status);
        $this->assertSame(FinancialStatus::NotDue, $unchangedOccurrence->financial_status);
        $this->assertNotNull($unchangedOccurrence->allocationOccupancy->expires_at);
        $this->assertSame($approved->booking_series_id, $unchangedOccurrence->booking_series_id);
    }

    public function test_recurring_creation_locks_allocation_units_and_equipment_in_primary_key_order(): void
    {
        $fixture = $this->bookableFixture(withEquipment: true);
        $secondEquipment = Equipment::factory()->for($fixture['equipment']->centre)->create(['quantity' => 4]);
        EquipmentRate::factory()->for($secondEquipment)->create();
        $lockingQueries = [];
        DB::listen(function (QueryExecuted $query) use (&$lockingQueries): void {
            if (str_contains(strtolower($query->sql), 'for update')) {
                $lockingQueries[] = $query->sql;
            }
        });

        $this->createSeries($this->customer(), $fixture['resource'], 2, [
            ['equipment_id' => $secondEquipment->id, 'quantity' => 1],
            ['equipment_id' => $fixture['equipment']->id, 'quantity' => 1],
        ]);

        $allocationUnitLock = collect($lockingQueries)->first(
            static fn (string $query): bool => str_contains($query, 'from `allocation_units`'),
        );
        $equipmentLock = collect($lockingQueries)->first(
            static fn (string $query): bool => str_contains($query, 'from `equipment`'),
        );

        $this->assertIsString($allocationUnitLock);
        $this->assertIsString($equipmentLock);
        $this->assertStringContainsString('order by `allocation_units`.`id` asc', $allocationUnitLock);
        $this->assertStringContainsString('order by `id` asc', $equipmentLock);
    }

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipmentSelections
     * @param  list<int>|null  $selectedOccurrenceIndexes
     */
    private function createSeries(
        User $customer,
        Resource $resource,
        int $occurrenceCount,
        array $equipmentSelections = [],
        ?array $selectedOccurrenceIndexes = null,
    ): CreateBookingSeriesResult {
        return app(CreateRecurringBookingRequest::class)->handle(
            $customer,
            $resource->id,
            CarbonImmutable::parse('2026-10-05 18:00:00', 'Europe/London'),
            CarbonImmutable::parse('2026-10-05 19:30:00', 'Europe/London'),
            new RecurrencePattern(RecurrenceFrequency::Weekly, 1, $occurrenceCount, 'Europe/London'),
            $equipmentSelections,
            $selectedOccurrenceIndexes,
        );
    }

    /**
     * @return array{resource: resource, equipment?: Equipment}
     */
    private function bookableFixture(bool $withEquipment = false, bool $withRate = true): array
    {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->for($centre)->create();
        $resource = $this->createBookableResource($facility, 'Sports Hall', $withRate);
        $units = AllocationUnit::factory()->count(2)->for($facility)->sequence(
            ['name' => 'Court A', 'code' => 'A'],
            ['name' => 'Court B', 'code' => 'B'],
        )->create();
        $resource->syncAllocationUnits(...$units);
        $fixture = ['resource' => $resource];

        if ($withEquipment) {
            $equipment = Equipment::factory()->for($centre)->create(['quantity' => 4]);
            EquipmentRate::factory()->for($equipment)->create();
            $fixture['equipment'] = $equipment;
        }

        return $fixture;
    }

    private function createBookableResource(Facility $facility, string $name, bool $withRate = true): Resource
    {
        $resource = Resource::factory()->for($facility)->create([
            'name' => $name,
            'slug' => str($name)->slug(),
            'setup_minutes' => 15,
            'cleanup_minutes' => 15,
        ]);

        if (! $facility->bookableHours()->where('day_of_week', DayOfWeek::Monday)->exists()) {
            FacilityBookableHour::factory()->for($facility)->create([
                'day_of_week' => DayOfWeek::Monday,
                'opens_at' => '08:00:00',
                'closes_at' => '22:00:00',
            ]);
        }

        ResourceBookableHour::factory()->for($resource)->create([
            'day_of_week' => DayOfWeek::Monday,
            'opens_at' => '08:00:00',
            'closes_at' => '22:00:00',
        ]);

        if ($withRate) {
            ResourceRate::factory()->for($resource)->create(['amount_minor' => 5000]);
        }

        return $resource;
    }

    private function customer(): User
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        return $customer;
    }
}
