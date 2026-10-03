<?php

namespace Tests\Feature\Filament;

use App\Actions\ApproveBooking;
use App\Actions\RejectBooking;
use App\Enums\AvailabilityReason;
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
use App\Models\BookingPriceSnapshot;
use App\Models\BookingSeries;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
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
        $this->assertSame('2026-10-02 12:00:00', $approved->payment_due_at?->toDateTimeString());
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

    public function test_management_review_identifies_a_recurring_occurrence_and_its_siblings(): void
    {
        [$first, $second, $third] = $this->protectedRecurringBookings();
        $manager = $this->assignedManager($first->centre);
        $this->actingAs($manager);

        Livewire::test(ViewBooking::class, ['record' => $first->getRouteKey()])
            ->assertSee($first->series->identifier)
            ->assertSee('Occurrence 1 of 3')
            ->assertSee('Every week')
            ->assertSee($first->reference)
            ->assertSee($second->reference)
            ->assertSee($third->reference);
    }

    public function test_management_decisions_on_recurring_occurrences_do_not_change_siblings(): void
    {
        [$first, $second, $third] = $this->protectedRecurringBookings();
        $manager = $this->assignedManager($first->centre);

        app(ApproveBooking::class)->handle($manager, $first);

        $this->assertSame(BookingStatus::Approved, $first->fresh()->status);
        $this->assertSame(BookingStatus::Requested, $second->fresh()->status);
        $this->assertSame(BookingStatus::Requested, $third->fresh()->status);

        app(RejectBooking::class)->handle($manager, $second, 'Operational closure.');

        $this->assertSame(BookingStatus::Approved, $first->fresh()->status);
        $this->assertSame(BookingStatus::Rejected, $second->fresh()->status);
        $this->assertSame(BookingStatus::Requested, $third->fresh()->status);
    }

    #[DataProvider('unauthorisedDecisions')]
    public function test_unauthorised_decisions_preserve_booking_protection_and_history(string $role, bool $assigned, bool $permission, string $decision): void
    {
        $booking = $this->protectedBooking(withEquipment: true);
        $actor = User::factory()->create();
        $actor->assignRole($role);
        $actor->assignedCentres()->attach($assigned ? $booking->centre : Centre::factory()->create());

        if (! $permission && $role === 'manager') {
            Role::findByName('manager')->revokePermissionTo('bookings.approve');
        }

        $before = $this->decisionState();
        $this->assertFalse(Gate::forUser($actor)->allows($decision, $booking));

        try {
            $this->decide($decision, $actor, $booking);
            $this->fail('An unauthorised decision must be refused.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $this->decisionState());
        }
    }

    /** @return iterable<string, array{string, bool, bool, string}> */
    public static function unauthorisedDecisions(): iterable
    {
        foreach (['approve', 'reject'] as $decision) {
            yield "customer $decision" => ['customer', true, false, $decision];
            yield "assistant $decision" => ['leisure-assistant', true, false, $decision];
            yield "manager without permission $decision" => ['manager', true, false, $decision];
            yield "manager outside centre $decision" => ['manager', false, true, $decision];
        }
    }

    #[TestWith(['approve', 'approve'])]
    #[TestWith(['approve', 'reject'])]
    #[TestWith(['reject', 'approve'])]
    #[TestWith(['reject', 'reject'])]
    public function test_terminal_decisions_reject_stale_repeat_or_cross_transition_without_changes(string $first, string $second): void
    {
        $booking = $this->protectedBooking(withEquipment: true);
        $manager = $this->assignedManager($booking->centre);
        $this->decide($first, $manager, $booking);
        $before = $this->decisionState();

        try {
            $this->decide($second, $manager, $booking);
            $this->fail('A stale Requested model must not permit another decision.');
        } catch (BookingLifecycleTransitionUnavailable) {
            $this->assertSame($before, $this->decisionState());
            $this->assertSame(1, Activity::query()->count());
        }
    }

    #[TestWith(['physical', 'missing'])]
    #[TestWith(['physical', 'expired'])]
    #[TestWith(['physical', 'expiry_boundary'])]
    #[TestWith(['physical', 'permanent'])]
    #[TestWith(['physical', 'starts_at'])]
    #[TestWith(['physical', 'ends_at'])]
    #[TestWith(['physical', 'units'])]
    #[TestWith(['equipment', 'missing'])]
    #[TestWith(['equipment', 'expired'])]
    #[TestWith(['equipment', 'expiry_boundary'])]
    #[TestWith(['equipment', 'permanent'])]
    #[TestWith(['equipment', 'starts_at'])]
    #[TestWith(['equipment', 'ends_at'])]
    #[TestWith(['equipment', 'quantity'])]
    #[TestWith(['equipment', 'equipment_id'])]
    public function test_invalid_protection_prevents_approval_without_changing_state_or_audit(string $kind, string $fault): void
    {
        $booking = $this->protectedBooking(withEquipment: true);
        $manager = $this->assignedManager($booking->centre);
        $protection = $kind === 'physical'
            ? $booking->allocationOccupancy()->sole()
            : $booking->equipmentAllocations()->sole();

        if ($kind === 'physical' && $fault === 'missing') {
            $booking->allocationOccupancy()->sole()->allocationUnits()->detach();
        }

        match ($fault) {
            'missing' => $protection->delete(),
            'expired' => $protection->update(['expires_at' => '2026-10-01 11:59:59']),
            'expiry_boundary' => $protection->update(['expires_at' => '2026-10-01 12:00:00']),
            'permanent' => $protection->update(['expires_at' => null]),
            'starts_at' => $protection->update(['starts_at' => '2026-10-05 18:01:00']),
            'ends_at' => $protection->update(['ends_at' => '2026-10-05 19:01:00']),
            'units' => $booking->allocationOccupancy()->sole()->allocationUnits()->sync(
                AllocationUnit::factory()->for($booking->facility)->create(),
            ),
            'quantity' => $protection->update(['quantity' => 1]),
            'equipment_id' => $protection->update([
                'equipment_id' => Equipment::factory()->for($booking->centre)->create()->id,
            ]),
        };
        $before = $this->decisionState();

        try {
            app(ApproveBooking::class)->handle($manager, $booking);
            $this->fail('Invalid protection must prevent approval.');
        } catch (BookingLifecycleTransitionUnavailable) {
            $this->assertSame($before, $this->decisionState());
        }
    }

    #[TestWith(['approve'])]
    #[TestWith(['reject'])]
    public function test_failure_after_audit_insertion_rolls_back_the_entire_decision(string $decision): void
    {
        $booking = $this->protectedBooking(withEquipment: true);
        $manager = $this->assignedManager($booking->centre);
        $before = $this->decisionState();
        $event = 'eloquent.created: '.Activity::class;
        Event::listen($event, function (): never {
            throw new RuntimeException('Simulated failure after audit insertion.');
        });

        try {
            $this->decide($decision, $manager, $booking);
            $this->fail('The injected failure must interrupt the decision.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated failure after audit insertion.', $exception->getMessage());
            $this->assertSame($before, $this->decisionState());
        } finally {
            Event::forget($event);
        }
    }

    #[TestWith([''])]
    #[TestWith([" \t\n "])]
    public function test_empty_rejection_reason_leaves_all_state_unchanged(string $reason): void
    {
        $booking = $this->protectedBooking(withEquipment: true);
        $manager = $this->assignedManager($booking->centre);
        $before = $this->decisionState();

        try {
            app(RejectBooking::class)->handle($manager, $booking, $reason);
            $this->fail('An empty reason must be refused.');
        } catch (ValidationException $exception) {
            $this->assertSame(['reason' => ['A rejection reason is required.']], $exception->errors());
            $this->assertSame($before, $this->decisionState());
        }
    }

    public function test_approval_preserves_resource_and_equipment_capacity_beyond_the_original_expiry(): void
    {
        $booking = $this->protectedBooking(withEquipment: true);
        $manager = $this->assignedManager($booking->centre);
        $occupancy = $booking->allocationOccupancy()->sole();
        $allocation = $booking->equipmentAllocations()->sole();

        app(ApproveBooking::class)->handle($manager, $booking);

        $this->assertSame($occupancy->id, $booking->allocationOccupancy()->sole()->id);
        $this->assertSame($allocation->id, $booking->equipmentAllocations()->sole()->id);
        $this->assertSame(2, $allocation->fresh()->quantity);
        $this->assertSame($occupancy->allocationUnits()->modelKeys(), $booking->resource->allocationUnits()->modelKeys());
        $availability = app(AvailabilityService::class)->check(
            $booking->resource,
            $booking->starts_at,
            $booking->ends_at,
            [new EquipmentRequirement($allocation->equipment, 3)],
            CarbonImmutable::parse('2026-10-04 12:00:00'),
        );
        $this->assertFalse($availability->isAvailable());
        $this->assertTrue($availability->hasReason(AvailabilityReason::ResourceConflict));
        $this->assertTrue($availability->hasReason(AvailabilityReason::EquipmentUnavailable));
    }

    public function test_changed_resource_availability_prevents_approval_without_promoting_protection(): void
    {
        $booking = $this->protectedBooking(withEquipment: true);
        $manager = $this->assignedManager($booking->centre);
        $booking->resource->update(['is_active' => false]);
        $before = $this->decisionState();

        try {
            app(ApproveBooking::class)->handle($manager, $booking);
            $this->fail('Approval must revalidate current availability.');
        } catch (BookingLifecycleTransitionUnavailable) {
            $this->assertSame($before, $this->decisionState());
        }
    }

    public function test_failure_during_rejection_restores_already_deleted_equipment_protection(): void
    {
        $booking = $this->protectedBooking(withEquipment: true);
        $manager = $this->assignedManager($booking->centre);
        $before = $this->decisionState();
        $event = 'eloquent.deleting: '.AllocationOccupancy::class;
        Event::listen($event, function () use ($booking): never {
            $this->assertFalse($booking->equipmentAllocations()->exists());
            throw new RuntimeException('Simulated physical protection deletion failure.');
        });

        try {
            app(RejectBooking::class)->handle($manager, $booking, 'Operational closure.');
            $this->fail('The injected failure must interrupt rejection.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated physical protection deletion failure.', $exception->getMessage());
            $this->assertSame($before, $this->decisionState());
        } finally {
            Event::forget($event);
        }
    }

    #[TestWith(['approve', 'booking.approved'])]
    #[TestWith(['reject', 'booking.rejected'])]
    public function test_review_actions_persist_decisions_and_remove_decided_requests(string $decision, string $event): void
    {
        $booking = $this->protectedBooking(withEquipment: true);
        $manager = $this->assignedManager($booking->centre);
        $this->actingAs($manager);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction($decision, data: $decision === 'reject' ? ['reason' => '  Operational closure.  '] : [])
            ->assertHasNoActionErrors()
            ->assertRedirect(BookingResource::getUrl('index'));

        $activity = $booking->activities()->sole();
        $this->assertSame($event, $activity->event);
        $this->assertTrue($activity->subject->is($booking));
        $this->assertTrue($activity->causer->is($manager));
        $this->assertSame('2026-10-01 12:00:00', $activity->created_at->toDateTimeString());
        $this->assertSame($decision === 'reject' ? ['reason' => 'Operational closure.'] : [
            'status' => 'approved',
            'payment_due_at' => '2026-10-02T12:00:00+00:00',
            'financial_status' => 'awaiting_payment',
        ], $activity->properties->all());
        $this->assertModelExists($booking);
        $this->assertFalse(BookingResource::getEloquentQuery()->whereKey($booking)->exists());
        $this->get(BookingResource::getUrl('view', ['record' => $booking]))->assertNotFound();
    }

    public function test_review_rejection_form_requires_a_reason_without_mutation(): void
    {
        $booking = $this->protectedBooking(withEquipment: true);
        $this->actingAs($this->assignedManager($booking->centre));
        $before = $this->decisionState();

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('reject', data: ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);

        $this->assertSame($before, $this->decisionState());
    }

    public function test_review_shows_persisted_context_and_hides_decisions_without_permission(): void
    {
        $booking = $this->protectedBooking(withEquipment: true);
        BookingPriceSnapshot::factory()->for($booking)->create();
        $manager = $this->assignedManager($booking->centre);
        Role::findByName('manager')->revokePermissionTo('bookings.approve');
        $this->actingAs($manager);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee($booking->customer->name)
            ->assertSee($booking->customer->email)
            ->assertSee($booking->centre->name)
            ->assertSee($booking->resource->name)
            ->assertSee($booking->equipmentRequests()->sole()->equipment->name)
            ->assertSee('5 Oct 2026, 18:00')
            ->assertSee('50.00')
            ->assertActionHidden('approve')
            ->assertActionHidden('reject')
            ->assertFormFieldDoesNotExist('financial_status');
    }

    public function test_guests_are_redirected_from_management_list_and_review(): void
    {
        $booking = $this->protectedBooking();

        $this->get(BookingResource::getUrl())->assertRedirect();
        $this->get(BookingResource::getUrl('view', ['record' => $booking]))->assertRedirect();
    }

    #[TestWith(['physical', false])]
    #[TestWith(['physical', true])]
    #[TestWith(['equipment', false])]
    #[TestWith(['equipment', true])]
    public function test_review_disables_approval_for_expired_or_missing_protection(string $kind, bool $missing): void
    {
        $booking = $this->protectedBooking(withEquipment: true);
        $this->actingAs($this->assignedManager($booking->centre));
        $protection = $kind === 'physical'
            ? $booking->allocationOccupancy()->sole()
            : $booking->equipmentAllocations()->sole();

        if ($missing) {
            if ($kind === 'physical') {
                $booking->allocationOccupancy()->sole()->allocationUnits()->detach();
            }

            $protection->delete();
        } else {
            $protection->update(['expires_at' => '2026-10-01 12:00:00']);
        }

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionDisabled('approve')
            ->assertActionEnabled('reject');
    }

    private function decide(string $decision, User $actor, Booking $booking): Booking
    {
        return $decision === 'approve'
            ? app(ApproveBooking::class)->handle($actor, $booking)
            : app(RejectBooking::class)->handle($actor, $booking, 'Operational closure.');
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function decisionState(): array
    {
        $state = [];

        foreach (['bookings', 'allocation_occupancies', 'allocation_occupancy_allocation_unit', 'equipment_allocations', 'activity_log'] as $table) {
            $state[$table] = DB::table($table)->orderBy($table === 'allocation_occupancy_allocation_unit' ? 'allocation_occupancy_id' : 'id')
                ->get()->map(fn (object $row): array => (array) $row)->all();
        }

        return $state;
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

    /**
     * @return array{Booking, Booking, Booking}
     */
    private function protectedRecurringBookings(): array
    {
        $first = $this->protectedBooking();
        $series = BookingSeries::factory()->create([
            'customer_id' => $first->customer_id,
            'centre_id' => $first->centre_id,
            'facility_id' => $first->facility_id,
            'resource_id' => $first->resource_id,
            'interval_weeks' => 1,
            'occurrence_count' => 3,
            'timezone' => 'Europe/London',
            'first_starts_at' => $first->starts_at,
            'first_ends_at' => $first->ends_at,
        ]);
        $first->update([
            'booking_series_id' => $series->id,
            'occurrence_index' => 1,
        ]);
        $first = $first->fresh() ?? $first;
        $bookings = [$first];

        foreach ([2 => '2026-10-12', 3 => '2026-10-19'] as $index => $date) {
            $booking = Booking::factory()->for($first->resource)->create([
                'booking_series_id' => $series->id,
                'occurrence_index' => $index,
                'customer_id' => $series->customer_id,
                'centre_id' => $series->centre_id,
                'facility_id' => $series->facility_id,
                'resource_id' => $series->resource_id,
                'starts_at' => "{$date} 18:00:00",
                'ends_at' => "{$date} 19:00:00",
            ]);
            $occupancy = AllocationOccupancy::factory()->for($booking)->create([
                'starts_at' => "{$date} 17:45:00",
                'ends_at' => "{$date} 19:15:00",
                'expires_at' => '2026-10-03 12:00:00',
            ]);
            $occupancy->allocationUnits()->attach($first->resource->allocationUnits()->pluck('allocation_units.id'));
            $bookings[] = $booking;
        }

        /** @var array{Booking, Booking, Booking} $bookings */
        return $bookings;
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
