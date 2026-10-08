<?php

namespace Tests\Feature\Database;

use App\Enums\DayOfWeek;
use App\Enums\FinancialStatus;
use App\Models\AllocationOccupancy;
use App\Models\AllocationUnit;
use App\Models\Booking;
use App\Models\BookingEquipment;
use App\Models\BookingPriceLine;
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
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MySqlBookingSubmissionTest extends TestCase
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

    public function test_an_authenticated_customer_submits_a_protected_booking_request_with_an_authoritative_price_snapshot(): void
    {
        $fixture = $this->bookableFixture();
        $customer = $this->customer();
        $response = $this->actingAs($customer)->postJson(route('bookings.store'), $this->payload($fixture['resource'], [
            $this->equipmentSelection($fixture['equipment'], 2),
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.status_label', 'Requested / Awaiting Management Approval');

        $booking = Booking::query()->sole();
        $snapshot = BookingPriceSnapshot::query()->sole();
        $occupancy = AllocationOccupancy::query()->sole();
        $equipmentRequest = BookingEquipment::query()->sole();
        $equipmentAllocation = EquipmentAllocation::query()->sole();

        $this->assertSame($customer->id, $booking->customer_id);
        $this->assertSame($fixture['resource']->facility_id, $booking->facility_id);
        $this->assertSame($fixture['resource']->facility->centre_id, $booking->centre_id);
        $this->assertSame(FinancialStatus::NotDue, $booking->financial_status);
        $this->assertMatchesRegularExpression('/^BKG-\d{8}$/', $booking->reference);
        $this->assertSame(7500, $snapshot->resource_amount_minor);
        $this->assertSame(1500, $snapshot->equipment_amount_minor);
        $this->assertSame(9000, $snapshot->final_total_minor);
        $this->assertSame(2, BookingPriceLine::query()->count());
        $this->assertSame($booking->id, $occupancy->booking_id);
        $this->assertSame(['A', 'B'], $occupancy->allocationUnits()->orderBy('code')->pluck('code')->all());
        $this->assertSame('2026-10-05 16:45:00', $occupancy->starts_at->toDateTimeString());
        $this->assertSame('2026-10-05 18:45:00', $occupancy->ends_at->toDateTimeString());
        $this->assertSame('2026-10-03 12:00:00', $occupancy->expires_at?->toDateTimeString());
        $this->assertSame($booking->id, $equipmentAllocation->booking_id);
        $this->assertSame($equipmentRequest->id, $equipmentAllocation->booking_equipment_id);
        $this->assertSame('2026-10-03 12:00:00', $equipmentAllocation->expires_at?->toDateTimeString());
    }

    #[DataProvider('localBookingPeriods')]
    public function test_customer_local_booking_input_is_persisted_as_the_correct_utc_instant(
        string $startsAt,
        string $endsAt,
        string $expectedUtcStartsAt,
        string $expectedUtcEndsAt,
    ): void {
        $fixture = $this->bookableFixture();
        $customer = $this->customer();
        $payload = $this->payload($fixture['resource']);
        $payload['starts_at'] = $startsAt;
        $payload['ends_at'] = $endsAt;

        $this->actingAs($customer)
            ->postJson(route('bookings.store'), $payload)
            ->assertCreated();

        $booking = Booking::query()->sole();

        $this->assertSame($expectedUtcStartsAt, $booking->starts_at->utc()->toDateTimeString());
        $this->assertSame($expectedUtcEndsAt, $booking->ends_at->utc()->toDateTimeString());
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function localBookingPeriods(): array
    {
        return [
            'BST' => ['2026-10-05 18:00:00', '2026-10-05 19:00:00', '2026-10-05 17:00:00', '2026-10-05 18:00:00'],
            'GMT' => ['2027-01-04 18:00:00', '2027-01-04 19:00:00', '2027-01-04 18:00:00', '2027-01-04 19:00:00'],
        ];
    }

    public function test_resource_unavailability_after_an_earlier_check_fails_without_persisting_a_partial_booking(): void
    {
        $fixture = $this->bookableFixture();
        $customer = $this->customer();
        $payload = $this->payload($fixture['resource']);

        $this->postJson(route('availability.check'), $payload)
            ->assertOk()
            ->assertJsonPath('data.available', true);

        $occupancy = AllocationOccupancy::factory()->create([
            'starts_at' => '2026-10-05 17:45:00',
            'ends_at' => '2026-10-05 19:45:00',
        ]);
        $occupancy->allocationUnits()->attach($fixture['units']->first());

        $this->actingAs($customer)->postJson(route('bookings.store'), $payload)
            ->assertConflict()
            ->assertJsonPath('message', 'This booking selection is no longer available.');

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_equipment', 0);
        $this->assertDatabaseCount('booking_price_snapshots', 0);
        $this->assertDatabaseCount('booking_price_lines', 0);
        $this->assertDatabaseCount('equipment_allocations', 0);
        $this->assertDatabaseCount('allocation_occupancies', 1);
    }

    public function test_equipment_unavailability_after_an_earlier_check_fails_without_persisting_a_partial_booking(): void
    {
        $fixture = $this->bookableFixture();
        $customer = $this->customer();
        $payload = $this->payload($fixture['resource'], [$this->equipmentSelection($fixture['equipment'], 2)]);

        $this->postJson(route('availability.check'), $payload)
            ->assertOk()
            ->assertJsonPath('data.available', true);

        EquipmentAllocation::factory()->for($fixture['equipment'])->create([
            'quantity' => 3,
            'starts_at' => '2026-10-05 18:00:00',
            'ends_at' => '2026-10-05 19:30:00',
        ]);

        $this->actingAs($customer)->postJson(route('bookings.store'), $payload)->assertConflict();

        $this->assertDatabaseEmpty('bookings');
        $this->assertDatabaseEmpty('booking_equipment');
        $this->assertDatabaseEmpty('booking_price_snapshots');
        $this->assertDatabaseEmpty('booking_price_lines');
        $this->assertDatabaseCount('equipment_allocations', 1);
        $this->assertDatabaseEmpty('allocation_occupancies');
    }

    public function test_browser_controlled_booking_ownership_status_and_pricing_values_are_rejected(): void
    {
        $fixture = $this->bookableFixture();
        $customer = $this->customer();
        $otherCustomer = $this->customer();

        $this->actingAs($customer)->postJson(route('bookings.store'), [
            ...$this->payload($fixture['resource']),
            'customer_id' => $otherCustomer->id,
            'booking_series_id' => 123,
            'occurrence_index' => 1,
            'status' => 'confirmed',
            'financial_status' => 'paid',
            'final_total_minor' => 1,
            'currency' => 'USD',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'customer_id',
                'booking_series_id',
                'occurrence_index',
                'status',
                'financial_status',
                'final_total_minor',
                'currency',
            ]);

        $this->assertDatabaseEmpty('bookings');
    }

    public function test_submission_recalculates_the_current_price_instead_of_using_an_earlier_quote(): void
    {
        $fixture = $this->bookableFixture();
        $customer = $this->customer();
        $payload = $this->payload($fixture['resource']);

        $this->postJson(route('pricing.quote'), $payload)
            ->assertOk()
            ->assertJsonPath('data.final_total_minor', 7500);

        $fixture['resourceRate']->update(['amount_minor' => 6500]);

        $this->actingAs($customer)->postJson(route('bookings.store'), $payload)->assertCreated();

        $this->assertSame(9750, BookingPriceSnapshot::query()->sole()->final_total_minor);
    }

    public function test_expired_booking_protection_is_ignored_by_the_existing_availability_service(): void
    {
        $fixture = $this->bookableFixture();
        $customer = $this->customer();

        $this->actingAs($customer)->postJson(route('bookings.store'), $this->payload($fixture['resource']))->assertCreated();

        $result = app(AvailabilityService::class)->check(
            $fixture['resource'],
            CarbonImmutable::parse('2026-10-05 18:00:00', config('app.timezone')),
            CarbonImmutable::parse('2026-10-05 19:30:00', config('app.timezone')),
            evaluatedAt: CarbonImmutable::parse('2026-10-03 12:00:00', config('app.timezone')),
        );

        $this->assertTrue($result->isAvailable());
    }

    public function test_the_booking_endpoint_requires_an_authenticated_customer_with_booking_creation_permission(): void
    {
        $fixture = $this->bookableFixture();

        $this->postJson(route('bookings.store'), $this->payload($fixture['resource']))
            ->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->postJson(route('bookings.store'), $this->payload($fixture['resource']))
            ->assertForbidden();

        $this->assertDatabaseEmpty('bookings');
    }

    /**
     * @return array{resource: resource, resourceRate: ResourceRate, equipment: Equipment, units: Collection<int, AllocationUnit>}
     */
    private function bookableFixture(): array
    {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->for($centre)->create();
        $resource = Resource::factory()->for($facility)->create([
            'setup_minutes' => 15,
            'cleanup_minutes' => 15,
        ]);
        FacilityBookableHour::factory()->for($facility)->create([
            'day_of_week' => DayOfWeek::Monday,
            'opens_at' => '08:00:00',
            'closes_at' => '21:00:00',
        ]);
        ResourceBookableHour::factory()->for($resource)->create([
            'day_of_week' => DayOfWeek::Monday,
            'opens_at' => '08:00:00',
            'closes_at' => '21:00:00',
        ]);
        $units = AllocationUnit::factory()->count(2)->for($facility)->sequence(
            ['name' => 'Court A', 'code' => 'A'],
            ['name' => 'Court B', 'code' => 'B'],
        )->create();
        $resource->syncAllocationUnits(...$units);
        $resourceRate = ResourceRate::factory()->for($resource)->create(['amount_minor' => 5000]);
        $equipment = Equipment::factory()->for($centre)->create(['quantity' => 4]);
        EquipmentRate::factory()->for($equipment)->create(['amount_minor' => 500]);

        return compact('resource', 'resourceRate', 'equipment', 'units');
    }

    private function customer(): User
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        return $customer;
    }

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipment
     * @return array{resource_id: int, starts_at: string, ends_at: string, equipment: list<array{equipment_id: int, quantity: int}>}
     */
    private function payload(Resource $resource, array $equipment = []): array
    {
        return [
            'resource_id' => $resource->id,
            'starts_at' => '2026-10-05 18:00:00',
            'ends_at' => '2026-10-05 19:30:00',
            'equipment' => $equipment,
        ];
    }

    /**
     * @return array{equipment_id: int, quantity: int}
     */
    private function equipmentSelection(Equipment $equipment, int $quantity): array
    {
        return [
            'equipment_id' => $equipment->id,
            'quantity' => $quantity,
        ];
    }
}
