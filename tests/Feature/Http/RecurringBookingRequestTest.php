<?php

namespace Tests\Feature\Http;

use App\Enums\DayOfWeek;
use App\Models\AllocationOccupancy;
use App\Models\AllocationUnit;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\Centre;
use App\Models\Equipment;
use App\Models\EquipmentRate;
use App\Models\Facility;
use App\Models\FacilityBookableHour;
use App\Models\Resource;
use App\Models\ResourceBookableHour;
use App\Models\ResourceRate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RecurringBookingRequestTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemRoleSeeder::class);
        config([
            'booking.provisional_hold_hours' => 48,
            'booking.recurrence_timezone' => 'Europe/London',
        ]);
        CarbonImmutable::setTestNow('2026-10-01 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_recurring_preview_requires_an_authenticated_customer_with_booking_permission(): void
    {
        $fixture = $this->bookableFixture();
        $payload = $this->payload($fixture['resource']);

        $this->postJson(route('bookings.recurring.preview'), $payload)
            ->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->postJson(route('bookings.recurring.preview'), $payload)
            ->assertForbidden();
    }

    public function test_recurring_inputs_accept_only_the_supported_weekly_pattern_and_valid_limits(): void
    {
        $fixture = $this->bookableFixture();
        $customer = $this->customer();

        $this->actingAs($customer)
            ->postJson(route('bookings.recurring.preview'), [
                ...$this->payload($fixture['resource']),
                'interval_weeks' => 0,
                'occurrence_count' => 105,
                'recurrence_frequency' => 'monthly',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'interval_weeks',
                'occurrence_count',
                'recurrence_frequency',
            ]);
    }

    public function test_preview_returns_every_server_generated_occurrence_in_chronological_order_with_price_and_equipment(): void
    {
        $fixture = $this->bookableFixture(withEquipment: true);

        $response = $this->actingAs($this->customer())
            ->postJson(route('bookings.recurring.preview'), [
                ...$this->payload(
                    $fixture['resource'],
                    [['equipment_id' => $fixture['equipment']->id, 'quantity' => 2]],
                ),
                'occurrence_count' => 4,
            ])
            ->assertOk()
            ->assertJsonPath('data.pattern.frequency', 'weekly')
            ->assertJsonPath('data.pattern.interval_weeks', 1)
            ->assertJsonPath('data.pattern.timezone', 'Europe/London')
            ->assertJsonPath('data.conflict_count', 0)
            ->assertJsonPath('data.valid_occurrence_indexes', [1, 2, 3, 4])
            ->assertJsonPath('data.occurrences.0.starts_at', '2026-10-05 18:00:00')
            ->assertJsonPath('data.occurrences.1.starts_at', '2026-10-12 18:00:00')
            ->assertJsonPath('data.occurrences.2.starts_at', '2026-10-19 18:00:00')
            ->assertJsonPath('data.occurrences.3.starts_at', '2026-10-26 18:00:00')
            ->assertJsonPath('data.occurrences.0.status', 'available')
            ->assertJsonPath('data.occurrences.0.price.currency', 'GBP')
            ->assertJsonPath('data.occurrences.0.price.total_minor', 9000)
            ->assertJsonPath('data.occurrences.0.equipment.0.name', 'Badminton nets')
            ->assertJsonPath('data.occurrences.0.equipment.0.quantity', 2);

        $this->assertCount(4, $response->json('data.occurrences'));
        $this->assertDatabaseEmpty('booking_series');
        $this->assertDatabaseEmpty('bookings');
    }

    public function test_preview_returns_all_conflicts_with_customer_safe_reasons_and_no_private_occupancy_details(): void
    {
        $fixture = $this->bookableFixture();
        $privateCustomer = User::factory()->create(['name' => 'Private Occupier']);
        $existingBooking = Booking::factory()->for($fixture['resource'])->for($privateCustomer, 'customer')->create([
            'reference' => 'BKG-PRIVATE',
            'starts_at' => '2026-10-12 17:00:00',
            'ends_at' => '2026-10-12 18:30:00',
        ]);
        $occupancy = AllocationOccupancy::factory()->for($existingBooking)->create([
            'starts_at' => '2026-10-12 17:00:00',
            'ends_at' => '2026-10-12 18:30:00',
        ]);
        $occupancy->allocationUnits()->attach($fixture['unit']);
        AvailabilityBlock::factory()->forResource($fixture['resource'])->create([
            'starts_at' => '2026-10-19 17:00:00',
            'ends_at' => '2026-10-19 18:30:00',
        ]);

        $response = $this->actingAs($this->customer())
            ->postJson(
                route('bookings.recurring.preview'),
                $this->payload($fixture['resource']),
            )
            ->assertOk()
            ->assertJsonPath('data.conflict_count', 2)
            ->assertJsonPath('data.valid_occurrence_indexes', [1])
            ->assertJsonPath('data.occurrences.0.status', 'available')
            ->assertJsonPath('data.occurrences.1.status', 'conflict')
            ->assertJsonPath('data.occurrences.1.conflict_reasons', ['resource_unavailable'])
            ->assertJsonPath('data.occurrences.2.status', 'conflict')
            ->assertJsonPath('data.occurrences.2.conflict_reasons', ['facility_block']);

        $this->assertCount(3, $response->json('data.occurrences'));
        $this->assertStringNotContainsString('Private Occupier', $response->getContent());
        $this->assertStringNotContainsString('BKG-PRIVATE', $response->getContent());
        $this->assertStringNotContainsString('customer_id', $response->getContent());
    }

    public function test_all_valid_occurrences_can_be_submitted_and_receive_an_awaiting_approval_confirmation(): void
    {
        $fixture = $this->bookableFixture();

        $response = $this->actingAs($this->customer())
            ->postJson(route('bookings.recurring.store'), [
                ...$this->payload($fixture['resource']),
                'submission_mode' => 'all_occurrences',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath(
                'data.status_label',
                'Requested / Awaiting Management Approval',
            )
            ->assertJsonPath('data.occurrence_count', 3)
            ->assertJsonPath('data.requested_occurrence_count', 3)
            ->assertJsonPath('data.first_date', '5 Oct 2026')
            ->assertJsonPath('data.last_date', '19 Oct 2026');

        $this->assertIsString($response->json('data.identifier'));
        $this->assertCount(3, $response->json('data.occurrences'));
        $this->assertStringNotContainsString('Confirmed', $response->getContent());
        $this->assertDatabaseCount('booking_series', 1);
        $this->assertDatabaseCount('bookings', 3);
        $this->assertDatabaseCount('booking_price_snapshots', 3);
        $this->assertDatabaseCount('allocation_occupancies', 3);
    }

    public function test_a_conflicted_series_cannot_submit_all_occurrences_without_resolution(): void
    {
        $fixture = $this->bookableFixture();
        $this->blockSecondOccurrence($fixture['resource']);

        $this->actingAs($this->customer())
            ->postJson(route('bookings.recurring.store'), [
                ...$this->payload($fixture['resource']),
                'submission_mode' => 'all_occurrences',
            ])
            ->assertConflict()
            ->assertJsonPath('data.conflict_count', 1)
            ->assertJsonPath('data.occurrences.1.status', 'conflict');

        $this->assertDatabaseEmpty('booking_series');
        $this->assertDatabaseEmpty('bookings');
    }

    public function test_customer_can_explicitly_submit_only_selected_available_occurrences(): void
    {
        $fixture = $this->bookableFixture();
        $this->blockSecondOccurrence($fixture['resource']);

        $response = $this->actingAs($this->customer())
            ->postJson(route('bookings.recurring.store'), [
                ...$this->payload($fixture['resource']),
                'submission_mode' => 'available_occurrences',
                'selected_occurrence_indexes' => [1, 3],
            ])
            ->assertCreated()
            ->assertJsonPath('data.occurrence_count', 2)
            ->assertJsonPath('data.requested_occurrence_count', 3)
            ->assertJsonPath('data.occurrences.0.index', 1)
            ->assertJsonPath('data.occurrences.1.index', 3);

        $seriesId = $response->json('data.identifier');
        $this->assertDatabaseHas('booking_series', [
            'identifier' => $seriesId,
            'occurrence_count' => 3,
        ]);
        $this->assertSame(
            [1, 3],
            Booking::query()->orderBy('occurrence_index')->pluck('occurrence_index')->all(),
        );
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_selected_subset_cannot_include_a_conflicted_occurrence(): void
    {
        $fixture = $this->bookableFixture();
        $this->blockSecondOccurrence($fixture['resource']);

        $this->actingAs($this->customer())
            ->postJson(route('bookings.recurring.store'), [
                ...$this->payload($fixture['resource']),
                'submission_mode' => 'available_occurrences',
                'selected_occurrence_indexes' => [1, 2, 3],
            ])
            ->assertConflict()
            ->assertJsonPath('data.conflict_count', 1);

        $this->assertDatabaseEmpty('booking_series');
        $this->assertDatabaseEmpty('bookings');
    }

    public function test_stale_availability_is_revalidated_and_the_selected_set_fails_atomically(): void
    {
        $fixture = $this->bookableFixture();
        $customer = $this->customer();

        $this->actingAs($customer)
            ->postJson(
                route('bookings.recurring.preview'),
                $this->payload($fixture['resource']),
            )
            ->assertOk()
            ->assertJsonPath('data.conflict_count', 0);

        $this->blockSecondOccurrence($fixture['resource']);

        $this->actingAs($customer)
            ->postJson(route('bookings.recurring.store'), [
                ...$this->payload($fixture['resource']),
                'submission_mode' => 'all_occurrences',
            ])
            ->assertConflict()
            ->assertJsonPath('data.conflict_count', 1)
            ->assertJsonPath('data.occurrences.1.status', 'conflict');

        $this->assertDatabaseEmpty('booking_series');
        $this->assertDatabaseEmpty('bookings');
    }

    public function test_submission_recalculates_each_selected_occurrence_price(): void
    {
        $fixture = $this->bookableFixture();
        $customer = $this->customer();

        $this->actingAs($customer)
            ->postJson(
                route('bookings.recurring.preview'),
                $this->payload($fixture['resource']),
            )
            ->assertOk()
            ->assertJsonPath('data.occurrences.0.price.total_minor', 7500);

        $fixture['rate']->update(['amount_minor' => 6000]);

        $response = $this->actingAs($customer)
            ->postJson(route('bookings.recurring.store'), [
                ...$this->payload($fixture['resource']),
                'submission_mode' => 'all_occurrences',
            ])
            ->assertCreated();

        $this->assertSame(9000, $response->json('data.occurrences.0.price.total_minor'));
        $this->assertSame(
            [9000, 9000, 9000],
            Booking::query()
                ->with('priceSnapshot')
                ->orderBy('occurrence_index')
                ->get()
                ->pluck('priceSnapshot.final_total_minor')
                ->all(),
        );
    }

    public function test_browser_controlled_ownership_status_availability_and_price_are_rejected(): void
    {
        $fixture = $this->bookableFixture();

        $this->actingAs($this->customer())
            ->postJson(route('bookings.recurring.store'), [
                ...$this->payload($fixture['resource']),
                'submission_mode' => 'all_occurrences',
                'customer_id' => User::factory()->create()->id,
                'centre_id' => $fixture['resource']->facility->centre_id,
                'status' => 'confirmed',
                'availability' => true,
                'price' => ['total_minor' => 1],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'customer_id',
                'centre_id',
                'status',
                'availability',
                'price',
            ]);

        $this->assertDatabaseEmpty('booking_series');
        $this->assertDatabaseEmpty('bookings');
    }

    public function test_duplicate_recurring_submission_does_not_create_a_second_series(): void
    {
        $fixture = $this->bookableFixture();
        $customer = $this->customer();
        $payload = [
            ...$this->payload($fixture['resource']),
            'submission_mode' => 'all_occurrences',
        ];

        $this->actingAs($customer)
            ->postJson(route('bookings.recurring.store'), $payload)
            ->assertCreated();

        $this->actingAs($customer)
            ->postJson(route('bookings.recurring.store'), $payload)
            ->assertConflict();

        $this->assertDatabaseCount('booking_series', 1);
        $this->assertDatabaseCount('bookings', 3);
    }

    public function test_available_occurrence_intent_is_required_and_must_stay_inside_the_generated_series(): void
    {
        $fixture = $this->bookableFixture();
        $customer = $this->customer();

        $this->actingAs($customer)
            ->postJson(
                route('bookings.recurring.store'),
                $this->payload($fixture['resource']),
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('submission_mode');

        $this->actingAs($customer)
            ->postJson(route('bookings.recurring.store'), [
                ...$this->payload($fixture['resource']),
                'submission_mode' => 'available_occurrences',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('selected_occurrence_indexes');

        $this->actingAs($customer)
            ->postJson(route('bookings.recurring.store'), [
                ...$this->payload($fixture['resource']),
                'submission_mode' => 'available_occurrences',
                'selected_occurrence_indexes' => [1, 4],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('selected_occurrence_indexes.1');
    }

    /**
     * @return array{resource: resource, unit: AllocationUnit, rate: ResourceRate, equipment?: Equipment}
     */
    private function bookableFixture(bool $withEquipment = false): array
    {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->for($centre)->create();
        $resource = Resource::factory()->for($facility)->create([
            'setup_minutes' => 0,
            'cleanup_minutes' => 0,
        ]);
        $unit = AllocationUnit::factory()->for($facility)->create();
        $resource->syncAllocationUnits($unit);
        FacilityBookableHour::factory()->for($facility)->create([
            'day_of_week' => DayOfWeek::Monday,
            'opens_at' => '08:00:00',
            'closes_at' => '22:00:00',
        ]);
        ResourceBookableHour::factory()->for($resource)->create([
            'day_of_week' => DayOfWeek::Monday,
            'opens_at' => '08:00:00',
            'closes_at' => '22:00:00',
        ]);
        $rate = ResourceRate::factory()->for($resource)->create([
            'amount_minor' => 5000,
        ]);
        $fixture = compact('resource', 'unit', 'rate');

        if ($withEquipment) {
            $equipment = Equipment::factory()->for($centre)->create([
                'name' => 'Badminton nets',
                'quantity' => 4,
            ]);
            EquipmentRate::factory()->for($equipment)->create([
                'amount_minor' => 500,
            ]);
            $fixture['equipment'] = $equipment;
        }

        return $fixture;
    }

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipment
     * @return array<string, mixed>
     */
    private function payload(Resource $resource, array $equipment = []): array
    {
        return [
            'resource_id' => $resource->id,
            'starts_at' => '2026-10-05 18:00:00',
            'ends_at' => '2026-10-05 19:30:00',
            'interval_weeks' => 1,
            'occurrence_count' => 3,
            'timezone' => 'Europe/London',
            'equipment' => $equipment,
        ];
    }

    private function customer(): User
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        return $customer;
    }

    private function blockSecondOccurrence(Resource $resource): void
    {
        AvailabilityBlock::factory()->forResource($resource)->create([
            'starts_at' => '2026-10-12 17:00:00',
            'ends_at' => '2026-10-12 18:30:00',
        ]);
    }
}
