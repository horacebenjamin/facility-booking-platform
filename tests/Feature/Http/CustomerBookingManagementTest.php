<?php

namespace Tests\Feature\Http;

use App\Actions\CreateBookingRequest;
use App\Enums\AttendanceState;
use App\Enums\BookingStatus;
use App\Enums\DayOfWeek;
use App\Enums\FinancialStatus;
use App\Enums\PaymentStatus;
use App\Models\AllocationOccupancy;
use App\Models\AllocationUnit;
use App\Models\Booking;
use App\Models\BookingPriceSnapshot;
use App\Models\BookingSeries;
use App\Models\Centre;
use App\Models\Facility;
use App\Models\FacilityBookableHour;
use App\Models\Payment;
use App\Models\Resource;
use App\Models\ResourceBookableHour;
use App\Models\ResourceRate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class CustomerBookingManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemRoleSeeder::class);
        $this->travelTo('2026-10-01 12:00:00');
        config(['booking.cancellation.minimum_notice_minutes' => 0]);
    }

    public function test_customer_booking_index_presents_all_supported_customer_states_and_only_owned_bookings(): void
    {
        $customer = $this->customer();
        $other = $this->customer();
        $fixture = $this->bookableFixture();

        $this->booking($customer, $fixture, ['starts_at' => '2026-10-05 18:00:00', 'ends_at' => '2026-10-05 19:00:00']);
        $this->booking($customer, $fixture, [
            'status' => BookingStatus::Approved,
            'financial_status' => FinancialStatus::AwaitingPayment,
            'payment_due_at' => '2026-09-30 12:00:00',
            'starts_at' => '2026-10-05 19:00:00',
            'ends_at' => '2026-10-05 20:00:00',
        ]);
        $this->booking($customer, $fixture, [
            'status' => BookingStatus::Confirmed,
            'financial_status' => FinancialStatus::Paid,
            'starts_at' => '2026-10-05 20:00:00',
            'ends_at' => '2026-10-05 21:00:00',
        ]);
        $this->booking($customer, $fixture, [
            'status' => BookingStatus::Confirmed,
            'attendance_state' => AttendanceState::Completed,
            'arrived_at' => '2026-09-30 18:00:00',
            'completed_at' => '2026-09-30 19:00:00',
            'starts_at' => '2026-10-05 21:00:00',
            'ends_at' => '2026-10-05 22:00:00',
        ]);
        $this->booking($customer, $fixture, [
            'status' => BookingStatus::Cancelled,
            'starts_at' => '2026-10-05 22:00:00',
            'ends_at' => '2026-10-05 23:00:00',
        ]);
        $this->booking($customer, $fixture, [
            'status' => BookingStatus::Rejected,
            'starts_at' => '2026-10-06 18:00:00',
            'ends_at' => '2026-10-06 19:00:00',
        ]);
        $this->booking($other, $fixture, ['starts_at' => '2026-10-06 19:00:00', 'ends_at' => '2026-10-06 20:00:00']);

        $this->withoutVite()->actingAs($customer)->get(route('bookings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('bookings/Index')
                ->has('bookings', 6)
                ->where('bookings.0.status', 'rejected')
                ->where('bookings.1.status', 'cancelled')
                ->where('bookings.2.status', 'completed')
                ->where('bookings.3.status', 'confirmed')
                ->where('bookings.4.status', 'expired')
                ->where('bookings.5.status', 'requested')
                ->missing('bookings.0.activities')
                ->missing('bookings.0.payments'));
    }

    public function test_customer_can_view_owned_booking_detail_with_safe_history_and_recurring_context(): void
    {
        $customer = $this->customer();
        $fixture = $this->bookableFixture();
        $series = BookingSeries::factory()->create([
            'customer_id' => $customer->id,
            'centre_id' => $fixture['centre']->id,
            'facility_id' => $fixture['facility']->id,
            'resource_id' => $fixture['resource']->id,
            'first_starts_at' => '2026-10-05 18:00:00',
            'first_ends_at' => '2026-10-05 19:00:00',
        ]);
        $booking = $this->booking($customer, $fixture, [
            'booking_series_id' => $series->id,
            'occurrence_index' => 1,
            'starts_at' => '2026-10-05 18:00:00',
            'ends_at' => '2026-10-05 19:00:00',
        ]);
        $this->booking($customer, $fixture, [
            'booking_series_id' => $series->id,
            'occurrence_index' => 2,
            'starts_at' => '2026-10-12 18:00:00',
            'ends_at' => '2026-10-12 19:00:00',
        ]);
        activity('booking')->performedOn($booking)->causedBy($customer)->event('booking.requested')->log('Request received');
        activity('booking')->performedOn($booking)->causedBy($customer)->event('booking.arrived')->withProperties(['staff_note' => 'Internal note'])->log('Internal attendance event');

        $this->withoutVite()->actingAs($customer)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('bookings/Show')
                ->where('booking.id', $booking->id)
                ->where('booking.starts_at', '2026-10-05T18:00:00+00:00')
                ->where('booking.ends_at', '2026-10-05T19:00:00+00:00')
                ->where('booking.is_recurring', true)
                ->where('booking.recurring.occurrence_count', 4)
                ->has('booking.recurring.occurrences', 2)
                ->where('booking.recurring.occurrences.0.starts_at', '2026-10-05T18:00:00+00:00')
                ->has('booking.history', 1)
                ->where('booking.history.0.title', 'Booking request submitted')
                ->missing('booking.history.0.staff_note')
                ->missing('booking.activities')
                ->missing('booking.payments'));
    }

    public function test_customer_cannot_view_or_mutate_another_customers_booking_by_id(): void
    {
        $owner = $this->customer();
        $other = $this->customer();
        $booking = $this->booking($owner, $this->bookableFixture());

        $this->actingAs($other)->get(route('bookings.show', $booking))->assertNotFound();
        $this->actingAs($other)->post(route('bookings.cancel', $booking), ['reason' => 'Not mine'])->assertNotFound();
        $this->actingAs($other)->patch(route('bookings.amend', $booking), [
            'scope' => 'occurrence',
            'starts_at' => '2026-10-05 18:00:00',
            'ends_at' => '2026-10-05 19:00:00',
        ])->assertNotFound();
    }

    public function test_customer_can_cancel_a_permitted_booking_without_mutating_financial_state_and_the_action_is_audited(): void
    {
        $customer = $this->customer();
        $fixture = $this->bookableFixture();
        $booking = $this->booking($customer, $fixture);
        $booking->update(['financial_status' => FinancialStatus::NotDue]);
        $occupancy = AllocationOccupancy::factory()->for($booking)->create([
            'starts_at' => $booking->starts_at,
            'ends_at' => $booking->ends_at,
            'expires_at' => now()->addDay(),
        ]);
        $occupancy->allocationUnits()->attach($fixture['unit']->id);

        $this->actingAs($customer)->post(route('bookings.cancel', $booking), ['reason' => 'Plans changed'])
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame(FinancialStatus::NotDue, $booking->fresh()->financial_status);
        $this->assertDatabaseMissing('allocation_occupancies', ['booking_id' => $booking->id]);
        $audit = Activity::query()->where('subject_type', Booking::class)->where('subject_id', $booking->id)->where('event', 'booking.cancelled')->sole();
        $this->assertSame('Plans changed', $audit->getProperty('reason'));
        $this->assertSame('none', $audit->getProperty('financial_follow_up'));
    }

    public function test_customer_cancellation_preserves_a_paid_financial_record_for_manual_follow_up(): void
    {
        $customer = $this->customer();
        $booking = $this->booking($customer, $this->bookableFixture(), [
            'status' => BookingStatus::Confirmed,
            'financial_status' => FinancialStatus::Paid,
        ]);
        $payment = Payment::factory()->for($booking)->create([
            'customer_id' => $customer->id,
            'status' => PaymentStatus::Succeeded,
            'succeeded_at' => now(),
        ]);

        $this->actingAs($customer)->post(route('bookings.cancel', $booking), ['reason' => 'No longer needed'])
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame(FinancialStatus::Paid, $booking->fresh()->financial_status);
        $this->assertModelExists($payment->fresh());
        $audit = Activity::query()->where('subject_id', $booking->id)->where('event', 'booking.cancelled')->sole();
        $this->assertSame('manual_review_required', $audit->getProperty('financial_follow_up'));
    }

    public function test_customer_cannot_cancel_a_completed_booking(): void
    {
        $customer = $this->customer();
        $booking = $this->booking($customer, $this->bookableFixture(), [
            'status' => BookingStatus::Confirmed,
            'attendance_state' => AttendanceState::Completed,
            'arrived_at' => '2026-09-30 18:00:00',
            'completed_at' => '2026-09-30 19:00:00',
        ]);

        $this->actingAs($customer)->post(route('bookings.cancel', $booking), ['reason' => 'Too late'])
            ->assertRedirect()
            ->assertSessionHasErrors('booking');
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertDatabaseMissing('activity_log', ['event' => 'booking.cancelled']);
    }

    public function test_customer_amendment_revalidates_availability_and_rolls_back_on_conflict(): void
    {
        $customer = $this->customer();
        $fixture = $this->bookableFixture();
        $booking = app(CreateBookingRequest::class)->handle(
            $customer,
            $fixture['resource']->id,
            CarbonImmutable::parse('2026-10-05 18:00:00'),
            CarbonImmutable::parse('2026-10-05 19:00:00'),
        );

        $conflict = AllocationOccupancy::factory()->create([
            'starts_at' => '2026-10-05 19:00:00',
            'ends_at' => '2026-10-05 20:00:00',
            'expires_at' => null,
        ]);
        $conflict->allocationUnits()->attach($fixture['unit']->id);
        $originalSnapshotId = $booking->priceSnapshot->id;

        $this->actingAs($customer)->patchJson(route('bookings.amend', $booking), [
            'scope' => 'occurrence',
            'starts_at' => '2026-10-05 19:00:00',
            'ends_at' => '2026-10-05 20:00:00',
        ])->assertRedirect()->assertSessionHasErrors('booking');

        $this->assertSame('2026-10-05 18:00:00', $booking->fresh()->starts_at->toDateTimeString());
        $this->assertSame($originalSnapshotId, $booking->fresh()->priceSnapshot->id);
        $this->assertDatabaseMissing('activity_log', ['event' => 'booking.amended']);
    }

    public function test_customer_can_amend_a_permitted_booking_and_rebuilds_its_authoritative_protection(): void
    {
        $customer = $this->customer();
        $fixture = $this->bookableFixture();
        $booking = app(CreateBookingRequest::class)->handle(
            $customer,
            $fixture['resource']->id,
            CarbonImmutable::parse('2026-10-05 18:00:00'),
            CarbonImmutable::parse('2026-10-05 19:00:00'),
        );

        $this->actingAs($customer)->patch(route('bookings.amend', $booking), [
            'scope' => 'occurrence',
            'starts_at' => '2026-10-05 19:00:00',
            'ends_at' => '2026-10-05 20:00:00',
        ])->assertRedirect(route('bookings.show', $booking));

        $amended = $booking->fresh();
        $this->assertSame('2026-10-05 19:00:00', $amended->starts_at->toDateTimeString());
        $this->assertSame('2026-10-05 20:00:00', $amended->ends_at->toDateTimeString());
        $this->assertSame('2026-10-05 19:00:00', $amended->allocationOccupancy->starts_at->toDateTimeString());
        $this->assertSame(1, BookingPriceSnapshot::query()->where('booking_id', $booking->id)->count());
        $this->assertSame(1, Activity::query()->where('subject_id', $booking->id)->where('event', 'booking.amended')->count());
    }

    public function test_customer_amendment_updates_only_the_explicit_recurring_occurrence(): void
    {
        $customer = $this->customer();
        $fixture = $this->bookableFixture();
        $booking = app(CreateBookingRequest::class)->handle(
            $customer,
            $fixture['resource']->id,
            CarbonImmutable::parse('2026-10-05 18:00:00'),
            CarbonImmutable::parse('2026-10-05 19:00:00'),
        );

        $this->actingAs($customer)->patchJson(route('bookings.amend', $booking), [
            'scope' => 'series',
            'starts_at' => '2026-10-05 19:00:00',
            'ends_at' => '2026-10-05 20:00:00',
        ])->assertUnprocessable()->assertJsonValidationErrors('scope');

        $this->assertSame('2026-10-05 18:00:00', $booking->fresh()->starts_at->toDateTimeString());
        $this->assertSame(1, BookingPriceSnapshot::query()->where('booking_id', $booking->id)->count());
    }

    /** @return array{centre: Centre, facility: Facility, resource: resource, unit: AllocationUnit} */
    private function bookableFixture(): array
    {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->for($centre)->create();
        $resource = Resource::factory()->for($facility)->create();
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
        $unit = AllocationUnit::factory()->for($facility)->create();
        $resource->syncAllocationUnits($unit);
        ResourceRate::factory()->for($resource)->create(['amount_minor' => 5000]);

        return compact('centre', 'facility', 'resource', 'unit');
    }

    private function customer(): User
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        return $customer;
    }

    /** @param array<string, mixed> $attributes */
    private function booking(User $customer, array $fixture, array $attributes = []): Booking
    {
        return Booking::factory()->create([
            'customer_id' => $customer->id,
            'centre_id' => $fixture['centre']->id,
            'facility_id' => $fixture['facility']->id,
            'resource_id' => $fixture['resource']->id,
            ...$attributes,
        ]);
    }
}
