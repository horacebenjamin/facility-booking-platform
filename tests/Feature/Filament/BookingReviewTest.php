<?php

namespace Tests\Feature\Filament;

use App\Actions\ApproveBooking;
use App\Actions\RejectBooking;
use App\Enums\BookingStatus;
use App\Enums\DayOfWeek;
use App\Enums\FinancialStatus;
use App\Exceptions\BookingLifecycleTransitionUnavailable;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Models\AllocationOccupancy;
use App\Models\AllocationUnit;
use App\Models\Booking;
use App\Models\BookingEquipment;
use App\Models\Centre;
use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use App\Models\Facility;
use App\Models\FacilityBookableHour;
use App\Models\Resource;
use App\Models\ResourceBookableHour;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\EquipmentRequirement;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingReviewTest extends TestCase
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

    public function test_authorised_manager_can_see_pending_requests_for_an_assigned_centre(): void
    {
        $manager = $this->manager();
        $assignedBooking = $this->protectedBooking();
        $otherBooking = $this->protectedBooking();
        $manager->assignedCentres()->attach($assignedBooking->centre);

        $this->actingAs($manager);

        $this->assertSame([$assignedBooking->id], BookingResource::getEloquentQuery()->pluck('id')->all());
        $this->get(BookingResource::getUrl())->assertSee($assignedBooking->reference)->assertDontSee($otherBooking->reference);
    }

    public function test_manager_cannot_review_a_booking_outside_their_centre_scope(): void
    {
        $manager = $this->manager();
        $assignedBooking = $this->protectedBooking();
        $otherBooking = $this->protectedBooking();
        $manager->assignedCentres()->attach($assignedBooking->centre);

        $this->actingAs($manager);

        $this->assertTrue(Gate::allows('view', $assignedBooking));
        $this->assertFalse(Gate::allows('view', $otherBooking));
        $this->get(BookingResource::getUrl('view', ['record' => $otherBooking]))->assertNotFound();
    }

    public function test_customer_cannot_access_management_booking_review(): void
    {
        $booking = $this->protectedBooking();
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $this->actingAs($customer)
            ->get(BookingResource::getUrl('view', ['record' => $booking]))
            ->assertForbidden();
    }

    public function test_leisure_assistant_cannot_approve_a_booking(): void
    {
        $booking = $this->protectedBooking();
        $leisureAssistant = User::factory()->create();
        $leisureAssistant->assignRole('leisure-assistant');
        $leisureAssistant->assignedCentres()->attach($booking->centre);

        $this->expectException(AuthorizationException::class);

        app(ApproveBooking::class)->handle($leisureAssistant, $booking);
    }

    public function test_manager_without_booking_approval_capability_cannot_approve(): void
    {
        $booking = $this->protectedBooking();
        $manager = $this->manager();
        $manager->assignedCentres()->attach($booking->centre);
        Role::findByName('manager')->revokePermissionTo('bookings.approve');

        $this->expectException(AuthorizationException::class);

        app(ApproveBooking::class)->handle($manager, $booking);
    }

    public function test_manager_without_booking_approval_capability_cannot_reject(): void
    {
        $booking = $this->protectedBooking();
        $manager = $this->manager();
        $manager->assignedCentres()->attach($booking->centre);
        Role::findByName('manager')->revokePermissionTo('bookings.approve');

        $this->expectException(AuthorizationException::class);

        app(RejectBooking::class)->handle($manager, $booking, 'Operational closure.');
    }

    public function test_valid_approval_moves_the_booking_to_approved_and_awaiting_payment_without_confirming_it(): void
    {
        $booking = $this->protectedBooking(withEquipment: true);
        $manager = $this->assignedManager($booking->centre);

        $approved = app(ApproveBooking::class)->handle($manager, $booking);

        $this->assertSame(BookingStatus::Approved, $approved->status);
        $this->assertSame(FinancialStatus::AwaitingPayment, $approved->financial_status);
        $this->assertNotSame('confirmed', $approved->status->value);
        $this->assertNull($approved->allocationOccupancy()->sole()->expires_at);
        $this->assertNull($approved->equipmentAllocations()->sole()->expires_at);
    }

    public function test_valid_rejection_persists_the_reason_releases_protection_and_records_the_decision(): void
    {
        $booking = $this->protectedBooking(withEquipment: true);
        $manager = $this->assignedManager($booking->centre);

        $rejected = app(RejectBooking::class)->handle($manager, $booking, 'The facility is needed for urgent maintenance.');

        $this->assertSame(BookingStatus::Rejected, $rejected->status);
        $this->assertSame(FinancialStatus::NotDue, $rejected->financial_status);
        $this->assertDatabaseMissing('allocation_occupancies', ['booking_id' => $booking->id]);
        $this->assertDatabaseMissing('equipment_allocations', ['booking_id' => $booking->id]);

        $activity = Activity::query()->sole();

        $this->assertSame('booking.rejected', $activity->event);
        $this->assertSame($manager->id, $activity->causer_id);
        $this->assertSame($booking->id, $activity->subject_id);
        $this->assertSame('The facility is needed for urgent maintenance.', $activity->properties?->get('reason'));
        $this->assertSame('2026-10-01 12:00:00', $activity->created_at->toDateTimeString());
    }

    public function test_rejection_requires_a_reason(): void
    {
        $booking = $this->protectedBooking();
        $manager = $this->assignedManager($booking->centre);

        $this->expectException(ValidationException::class);

        app(RejectBooking::class)->handle($manager, $booking, '  ');
    }

    public function test_an_invalid_transition_and_duplicate_approval_are_rejected(): void
    {
        $booking = $this->protectedBooking();
        $manager = $this->assignedManager($booking->centre);

        app(ApproveBooking::class)->handle($manager, $booking);

        try {
            app(ApproveBooking::class)->handle($manager, $booking->fresh());
            $this->fail('A second approval must be rejected.');
        } catch (BookingLifecycleTransitionUnavailable) {
            $this->assertSame(BookingStatus::Approved, $booking->fresh()->status);
            $this->assertSame(1, Activity::query()->count());
        }
    }

    public function test_duplicate_rejection_is_rejected(): void
    {
        $booking = $this->protectedBooking();
        $manager = $this->assignedManager($booking->centre);

        app(RejectBooking::class)->handle($manager, $booking, 'Operational closure.');

        $this->expectException(BookingLifecycleTransitionUnavailable::class);

        app(RejectBooking::class)->handle($manager, $booking->fresh(), 'Repeated decision.');
    }

    public function test_expired_protection_prevents_approval(): void
    {
        $booking = $this->protectedBooking(expiresAt: '2026-10-01 11:59:59');
        $manager = $this->assignedManager($booking->centre);

        $this->expectException(BookingLifecycleTransitionUnavailable::class);

        app(ApproveBooking::class)->handle($manager, $booking);
    }

    public function test_expired_equipment_protection_prevents_approval(): void
    {
        $booking = $this->protectedBooking(withEquipment: true);
        $manager = $this->assignedManager($booking->centre);
        $booking->equipmentAllocations()->update(['expires_at' => '2026-10-01 11:59:59']);

        $this->expectException(BookingLifecycleTransitionUnavailable::class);

        app(ApproveBooking::class)->handle($manager, $booking);
    }

    public function test_rejection_makes_released_resource_and_equipment_capacity_available_immediately(): void
    {
        $booking = $this->protectedBooking(withEquipment: true);
        $manager = $this->assignedManager($booking->centre);
        $equipmentRequest = $booking->equipmentRequests()->sole();

        app(RejectBooking::class)->handle($manager, $booking, 'Operational closure.');

        $availability = app(AvailabilityService::class)->check(
            $booking->resource,
            $booking->starts_at,
            $booking->ends_at,
            [new EquipmentRequirement($equipmentRequest->equipment, $equipmentRequest->requested_quantity)],
            CarbonImmutable::now(config('app.timezone')),
        );

        $this->assertTrue($availability->isAvailable());
    }

    public function test_approval_records_actor_time_and_audit_history(): void
    {
        $booking = $this->protectedBooking();
        $manager = $this->assignedManager($booking->centre);

        app(ApproveBooking::class)->handle($manager, $booking);

        $activity = Activity::query()->sole();

        $this->assertSame('booking', $activity->log_name);
        $this->assertSame('booking.approved', $activity->event);
        $this->assertSame($manager->id, $activity->causer_id);
        $this->assertSame($booking->id, $activity->subject_id);
        $this->assertSame('2026-10-01 12:00:00', $activity->created_at->toDateTimeString());
    }

    public function test_management_review_uses_explicit_actions_without_a_generic_status_editor(): void
    {
        $booking = $this->protectedBooking();
        $manager = $this->assignedManager($booking->centre);

        $this->actingAs($manager);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionVisible(TestAction::make('approve'))
            ->assertActionVisible(TestAction::make('reject'))
            ->assertFormFieldDoesNotExist('status');

        $this->assertArrayNotHasKey('edit', BookingResource::getPages());
    }

    private function protectedBooking(bool $withEquipment = false, string $expiresAt = '2026-10-03 12:00:00'): Booking
    {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->for($centre)->create();
        $resource = Resource::factory()->for($facility)->create(['setup_minutes' => 15, 'cleanup_minutes' => 15]);
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
        $booking = Booking::factory()->for($resource)->create([
            'starts_at' => '2026-10-05 18:00:00',
            'ends_at' => '2026-10-05 19:00:00',
        ]);
        $occupancy = AllocationOccupancy::factory()->for($booking)->create([
            'starts_at' => '2026-10-05 17:45:00',
            'ends_at' => '2026-10-05 19:15:00',
            'expires_at' => $expiresAt,
        ]);
        $occupancy->allocationUnits()->attach($unit);

        if ($withEquipment) {
            $equipment = Equipment::factory()->for($centre)->create(['quantity' => 4]);
            $request = BookingEquipment::factory()->for($booking)->create([
                'centre_id' => $centre->id,
                'equipment_id' => $equipment->id,
                'requested_quantity' => 2,
            ]);
            EquipmentAllocation::factory()
                ->for($booking)
                ->for($request, 'bookingEquipment')
                ->for($equipment)
                ->create([
                    'quantity' => 2,
                    'starts_at' => '2026-10-05 18:00:00',
                    'ends_at' => '2026-10-05 19:00:00',
                    'expires_at' => $expiresAt,
                ]);
        }

        return $booking;
    }

    private function manager(): User
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        return $manager;
    }

    private function assignedManager(Centre $centre): User
    {
        $manager = $this->manager();
        $manager->assignedCentres()->attach($centre);

        return $manager;
    }
}
