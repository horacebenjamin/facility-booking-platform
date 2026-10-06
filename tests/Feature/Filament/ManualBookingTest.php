<?php

namespace Tests\Feature\Filament;

use App\Actions\ApproveBooking;
use App\Actions\CreateManualBooking;
use App\Actions\IssueInvoice;
use App\Actions\RecordManualInvoicePayment;
use App\Actions\SetCustomerInvoiceTerms;
use App\Enums\BookingStatus;
use App\Enums\DayOfWeek;
use App\Enums\FinancialStatus;
use App\Exceptions\BookingSubmissionUnavailable;
use App\Filament\Pages\CreateAssistedBooking;
use App\Models\AllocationUnit;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
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
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ManualBookingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemRoleSeeder::class);
        CarbonImmutable::setTestNow('2026-10-01 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_authorised_manager_creates_a_customer_owned_booking_with_staff_audit_attribution(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);
        $customer = $this->customer();

        $booking = $this->createManual($manager, $customer, $fixture);
        $activity = $booking->activities()->where('event', 'booking.requested')->sole();

        $this->assertSame($customer->id, $booking->customer_id);
        $this->assertSame($fixture['centre']->id, $booking->centre_id);
        $this->assertSame(BookingStatus::Requested, $booking->status);
        $this->assertSame(FinancialStatus::NotDue, $booking->financial_status);
        $this->assertSame($manager->id, $activity->causer_id);
        $this->assertSame($customer->id, $activity->properties['customer_id'] ?? null);
        $this->assertSame('staff_assisted', $activity->properties['booking_channel'] ?? null);
        $this->assertTrue(Gate::forUser($customer)->allows('viewCustomer', $booking));
        $this->assertSame($booking->id, $customer->fresh()->bookings()->sole()->id);
    }

    public function test_only_an_assigned_manager_can_access_the_assisted_booking_page_and_action(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);
        $customer = $this->customer();
        $assistant = User::factory()->create();
        $assistant->assignRole('leisure-assistant');
        $assistant->assignedCentres()->attach($fixture['centre']);

        $this->actingAs($manager)->get(CreateAssistedBooking::getUrl())->assertOk();
        $this->actingAs($assistant)->get(CreateAssistedBooking::getUrl())->assertForbidden();

        $this->expectException(AuthorizationException::class);
        $this->createManual($assistant, $customer, $fixture);
    }

    public function test_assisted_booking_page_previews_and_submits_through_the_shared_action(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);
        $customer = $this->customer();

        $this->actingAs($manager);

        Livewire::test(CreateAssistedBooking::class)
            ->set('customerId', $customer->id)
            ->set('centreId', $fixture['centre']->id)
            ->set('resourceId', $fixture['resource']->id)
            ->set('startsAt', '2026-10-05T18:00')
            ->set('endsAt', '2026-10-05T19:30')
            ->call('preview')
            ->assertSet('quote.final_total_minor', 7500)
            ->call('submit')
            ->assertSet('successReference', Booking::query()->sole()->reference);

        $this->assertDatabaseHas('bookings', ['customer_id' => $customer->id, 'centre_id' => $fixture['centre']->id]);
    }

    public function test_manager_cannot_create_a_booking_in_an_unassigned_centre(): void
    {
        $fixture = $this->bookableFixture();
        $foreign = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);

        $this->expectException(AuthorizationException::class);
        $this->createManual($manager, $this->customer(), $foreign);
    }

    public function test_manual_booking_rejects_resource_conflicts_and_leaves_no_partial_records(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);
        $this->createManual($manager, $this->customer(), $fixture);

        try {
            $this->createManual($manager, $this->customer(), $fixture);
            $this->fail('A conflicting resource booking should be rejected.');
        } catch (BookingSubmissionUnavailable) {
            $this->assertDatabaseCount('bookings', 1);
            $this->assertDatabaseCount('booking_price_snapshots', 1);
            $this->assertDatabaseCount('booking_price_lines', 1);
            $this->assertDatabaseCount('allocation_occupancies', 1);
        }
    }

    public function test_manual_booking_rejects_closures_and_exhausted_equipment(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);

        AvailabilityBlock::factory()->forResource($fixture['resource'])->create([
            'starts_at' => '2026-10-05 17:00:00',
            'ends_at' => '2026-10-05 20:00:00',
        ]);

        try {
            $this->createManual($manager, $this->customer(), $fixture);
            $this->fail('A blocked resource should be rejected.');
        } catch (BookingSubmissionUnavailable) {
            $this->assertDatabaseEmpty('bookings');
        }

        AvailabilityBlock::query()->delete();
        EquipmentAllocation::factory()->for($fixture['equipment'])->create([
            'quantity' => $fixture['equipment']->quantity,
            'starts_at' => '2026-10-05 18:00:00',
            'ends_at' => '2026-10-05 19:30:00',
        ]);

        $this->expectException(BookingSubmissionUnavailable::class);
        $this->createManual($manager, $this->customer(), $fixture, [
            ['equipment_id' => $fixture['equipment']->id, 'quantity' => 1],
        ]);
    }

    public function test_manual_booking_rejects_cross_centre_equipment(): void
    {
        $fixture = $this->bookableFixture();
        $foreign = Equipment::factory()->for(Centre::factory())->create(['quantity' => 2]);
        EquipmentRate::factory()->for($foreign)->create(['amount_minor' => 500]);
        $manager = $this->manager($fixture['centre']);

        $this->expectException(BookingSubmissionUnavailable::class);
        $this->createManual($manager, $this->customer(), $fixture, [
            ['equipment_id' => $foreign->id, 'quantity' => 1],
        ]);
    }

    public function test_authorised_price_override_is_snapshotted_and_audited(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);
        $booking = $this->createManual($manager, $this->customer(), $fixture, [], 6000, 'Community partnership rate');
        $snapshot = $booking->priceSnapshot()->firstOrFail();
        $activity = $booking->activities()->where('event', 'booking.requested')->sole();

        $this->assertSame(7500, $snapshot->calculated_total_minor);
        $this->assertSame(6000, $snapshot->final_total_minor);
        $this->assertSame($manager->id, $snapshot->override_responsible_user_id);
        $this->assertSame('Community partnership rate', $snapshot->override_reason);
        $this->assertSame(6000, $activity->properties['price_override']['adjusted_total_minor'] ?? null);
        $this->assertSame($manager->id, $activity->properties['price_override']['responsible_user_id'] ?? null);
    }

    public function test_price_override_requires_a_reason(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);

        $this->expectException(ValidationException::class);
        $this->createManual($manager, $this->customer(), $fixture, [], 6000);
    }

    public function test_assisted_booking_shows_an_inline_error_when_override_reason_is_missing(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);
        $customer = $this->customer();

        $this->actingAs($manager);

        Livewire::test(CreateAssistedBooking::class)
            ->set('customerId', $customer->id)
            ->set('centreId', $fixture['centre']->id)
            ->set('resourceId', $fixture['resource']->id)
            ->set('startsAt', '2026-10-05T18:00')
            ->set('endsAt', '2026-10-05T19:30')
            ->set('overrideAmountMinor', 6000)
            ->set('overrideReason', '')
            ->call('submit')
            ->assertHasErrors(['overrideReason' => 'required'])
            ->assertSee('The override reason field is required.');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_manual_booking_remains_compatible_with_invoice_and_manual_payment_workflows(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);
        $booking = $this->createManual($manager, $this->customer(), $fixture);

        app(SetCustomerInvoiceTerms::class)->handle($manager, $booking, true, 30);
        $confirmed = app(ApproveBooking::class)->handle($manager, $booking);
        $invoice = app(IssueInvoice::class)->handle($manager, [$confirmed->id]);
        $payment = app(RecordManualInvoicePayment::class)->handle($manager, $invoice, 'BANK-M18', 'Verified staff-assisted booking payment.');

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertSame(FinancialStatus::Paid, $booking->fresh()->financial_status);
        $this->assertSame($booking->customer_id, $invoice->customer_id);
        $this->assertSame($invoice->id, $payment->invoice_id);
    }

    /** @return array{centre: Centre, facility: Facility, resource: resource, equipment: Equipment, units: list<AllocationUnit>} */
    private function bookableFixture(): array
    {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->for($centre)->create();
        $resource = Resource::factory()->for($facility)->create(['setup_minutes' => 15, 'cleanup_minutes' => 15]);
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
        $units = AllocationUnit::factory()->count(2)->for($facility)->create();
        $resource->syncAllocationUnits(...$units);
        ResourceRate::factory()->for($resource)->create(['amount_minor' => 5000]);
        $equipment = Equipment::factory()->for($centre)->create(['quantity' => 4]);
        EquipmentRate::factory()->for($equipment)->create(['amount_minor' => 500]);

        return compact('centre', 'facility', 'resource', 'equipment', 'units');
    }

    private function manager(Centre $centre): User
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->assignedCentres()->attach($centre);

        return $manager;
    }

    private function customer(): User
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        return $customer;
    }

    /** @param array{centre: Centre, facility: Facility, resource: resource, equipment: Equipment, units: list<AllocationUnit>} $fixture */
    private function createManual(User $actor, User $customer, array $fixture, array $equipmentSelections = [], ?int $overrideAmountMinor = null, ?string $overrideReason = null): Booking
    {
        return app(CreateManualBooking::class)->handle(
            actor: $actor,
            customer: $customer,
            centreId: $fixture['centre']->id,
            resourceId: $fixture['resource']->id,
            startsAt: CarbonImmutable::parse('2026-10-05 18:00:00', config('app.timezone')),
            endsAt: CarbonImmutable::parse('2026-10-05 19:30:00', config('app.timezone')),
            equipmentSelections: $equipmentSelections,
            overrideAmountMinor: $overrideAmountMinor,
            overrideReason: $overrideReason,
        );
    }
}
