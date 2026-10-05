<?php

namespace Tests\Feature\Attendance;

use App\Actions\CompleteBooking;
use App\Actions\RecordArrival;
use App\Actions\RecordNoShow;
use App\Enums\AttendanceState;
use App\Enums\BookingStatus;
use App\Enums\FinancialStatus;
use App\Exceptions\AttendanceTransitionUnavailable;
use App\Models\Booking;
use App\Models\BookingPriceSnapshot;
use App\Models\BookingSeries;
use App\Models\Centre;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AttendanceWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemRoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-05 16:45:00', config('app.timezone')));
    }

    public function test_arrival_and_completion_record_actor_time_and_history_once_without_changing_commercial_state(): void
    {
        $booking = $this->booking();
        $staff = $this->assistant($booking->centre);
        $stale = $booking->replicate();
        $stale->id = $booking->id;
        $arrived = app(RecordArrival::class)->handle($staff, $booking);

        $this->assertSame(AttendanceState::Arrived, $arrived->attendance_state);
        $this->assertSame('2026-10-05 16:45:00', $arrived->arrived_at->toDateTimeString());
        $audit = $booking->activities()->sole();
        $this->assertSame('booking.arrived', $audit->event);
        $this->assertSame($staff->id, $audit->causer_id);
        $this->assertSame('expected', $audit->properties->get('old_attendance_state'));
        $this->assertSame('arrived', $audit->properties->get('new_attendance_state'));
        $this->assertNotNull($audit->properties->get('recorded_at'));
        $this->travelTo(CarbonImmutable::parse('2026-10-05 18:10:00', config('app.timezone')));

        $completed = app(CompleteBooking::class)->handle($staff, $stale);

        $this->assertSame(AttendanceState::Completed, $completed->attendance_state);
        $this->assertSame('2026-10-05 18:10:00', $completed->completed_at->toDateTimeString());
        $this->assertSame(BookingStatus::Confirmed, $completed->status);
        $this->assertSame(FinancialStatus::Paid, $completed->financial_status);
        $this->assertSame(['booking.arrived', 'booking.completed'], $booking->activities()->orderBy('id')->pluck('event')->all());
    }

    #[TestWith(['arrival', '2026-10-05 16:44:59'])]
    #[TestWith(['arrival', '2026-10-05 18:00:00'])]
    #[TestWith(['no-show', '2026-10-05 17:59:59'])]
    #[TestWith(['complete', '2026-10-05 18:09:59'])]
    public function test_timing_boundaries_reject_invalid_actions_without_audit(string $action, string $time): void
    {
        $booking = $this->booking();
        $staff = $this->assistant($booking->centre);
        if ($action === 'complete') {
            app(RecordArrival::class)->handle($staff, $booking);
        }
        $count = $booking->activities()->count();
        $this->travelTo(CarbonImmutable::parse($time, config('app.timezone')));

        try {
            $this->action($action)->handle($staff, $booking);
            $this->fail('Invalid timing must be rejected.');
        } catch (AttendanceTransitionUnavailable) {
            $this->assertSame($count, $booking->activities()->count());
            $this->assertNull($booking->fresh()->completed_at);
            $this->assertNull($booking->fresh()->no_show_recorded_at);
        }
    }

    public function test_no_show_at_exact_end_keeps_paid_booking_payment_and_price_unchanged(): void
    {
        $booking = $this->booking();
        $staff = $this->assistant($booking->centre);
        $snapshot = BookingPriceSnapshot::factory()->create(['booking_id' => $booking->id]);
        $payment = Payment::factory()->create(['booking_id' => $booking->id, 'status' => 'succeeded']);
        $beforeSnapshot = $snapshot->fresh()->getRawOriginal();
        $beforePayment = $payment->fresh()->getRawOriginal();
        $this->travelTo(CarbonImmutable::parse('2026-10-05 18:00:00', config('app.timezone')));

        $result = app(RecordNoShow::class)->handle($staff, $booking);

        $this->assertSame(AttendanceState::NoShow, $result->attendance_state);
        $this->assertSame('2026-10-05 18:00:00', $result->no_show_recorded_at->toDateTimeString());
        $this->assertSame(BookingStatus::Confirmed, $result->status);
        $this->assertSame(FinancialStatus::Paid, $result->financial_status);
        $this->assertSame($beforeSnapshot, $snapshot->fresh()->getRawOriginal());
        $this->assertSame($beforePayment, $payment->fresh()->getRawOriginal());
    }

    public function test_completion_preserves_real_invoice_obligation_without_notifications_or_money_changes(): void
    {
        $booking = $this->booking(['financial_status' => FinancialStatus::Invoiced, 'billing_method' => 'invoice', 'invoice_term_days' => 30]);
        $staff = $this->assistant($booking->centre);
        $invoice = Invoice::factory()->create(['customer_id' => $booking->customer_id]);
        $line = InvoiceLine::factory()->create(['booking_id' => $booking->id, 'invoice_id' => $invoice->id]);
        $snapshot = BookingPriceSnapshot::factory()->create(['booking_id' => $booking->id]);
        $beforeInvoice = $invoice->fresh()->getRawOriginal();
        $beforeLine = $line->fresh()->getRawOriginal();
        $beforeSnapshot = $snapshot->fresh()->getRawOriginal();
        app(RecordArrival::class)->handle($staff, $booking);
        $this->travelTo(CarbonImmutable::parse('2026-10-05 18:10:00', config('app.timezone')));

        $result = app(CompleteBooking::class)->handle($staff, $booking);

        $this->assertSame(FinancialStatus::Invoiced, $result->financial_status);
        $this->assertSame(BookingStatus::Confirmed, $result->status);
        $this->assertSame($beforeInvoice, $invoice->fresh()->getRawOriginal());
        $this->assertSame($beforeLine, $line->fresh()->getRawOriginal());
        $this->assertSame($beforeSnapshot, $snapshot->fresh()->getRawOriginal());
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('customer_communications', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    #[TestWith(['invoice_outstanding'])]
    #[TestWith(['invoiced'])]
    public function test_no_show_preserves_outstanding_invoice_obligation_and_existing_charge(string $financialStatus): void
    {
        $booking = $this->booking(['financial_status' => $financialStatus, 'billing_method' => 'invoice', 'invoice_term_days' => 30]);
        $staff = $this->assistant($booking->centre);
        $invoice = Invoice::factory()->create(['customer_id' => $booking->customer_id]);
        $line = InvoiceLine::factory()->create(['booking_id' => $booking->id, 'invoice_id' => $invoice->id]);
        $snapshot = BookingPriceSnapshot::factory()->create(['booking_id' => $booking->id]);
        $beforeInvoice = $invoice->fresh()->getRawOriginal();
        $beforeLine = $line->fresh()->getRawOriginal();
        $beforeSnapshot = $snapshot->fresh()->getRawOriginal();
        $this->travelTo(CarbonImmutable::parse('2026-10-05 18:00:00', config('app.timezone')));

        $result = app(RecordNoShow::class)->handle($staff, $booking);

        $this->assertSame($financialStatus, $result->financial_status->value);
        $this->assertSame(BookingStatus::Confirmed, $result->status);
        $this->assertSame(AttendanceState::NoShow, $result->attendance_state);
        $this->assertSame($beforeInvoice, $invoice->fresh()->getRawOriginal());
        $this->assertSame($beforeLine, $line->fresh()->getRawOriginal());
        $this->assertSame($beforeSnapshot, $snapshot->fresh()->getRawOriginal());
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('customer_communications', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    #[TestWith(['customer'])]
    #[TestWith(['manager'])]
    public function test_assignment_and_seeded_capabilities_do_not_bypass_operations_role(string $role): void
    {
        $booking = $this->booking();
        $actor = User::factory()->create();
        $actor->assignRole($role);
        $actor->assignedCentres()->attach($booking->centre);

        $this->expectException(AuthorizationException::class);
        app(RecordArrival::class)->handle($actor, $booking);
    }

    #[TestWith(['bookings.view'])]
    #[TestWith(['attendance.manage'])]
    public function test_missing_capability_is_denied(string $permission): void
    {
        $booking = $this->booking();
        $actor = $this->assistant($booking->centre);
        $actor->roles()->firstOrFail()->revokePermissionTo($permission);

        $this->expectException(AuthorizationException::class);
        app(RecordArrival::class)->handle($actor, $booking);
    }

    public function test_removed_assignment_is_checked_again_even_with_loaded_relations(): void
    {
        $booking = $this->booking();
        $staff = $this->assistant($booking->centre);
        $staff->load('assignedCentres');
        $staff->assignedCentres()->detach($booking->centre);

        $this->expectException(AuthorizationException::class);
        app(RecordArrival::class)->handle($staff, $booking);
    }

    public function test_forged_booking_model_cannot_replace_authoritative_centre_or_lifecycle(): void
    {
        $foreign = $this->booking();
        $local = Centre::factory()->create();
        $staff = $this->assistant($local);
        $foreign->centre_id = $local->id;
        $foreign->setRelation('centre', $local);

        $this->expectException(AuthorizationException::class);
        app(RecordArrival::class)->handle($staff, $foreign);
    }

    #[TestWith(['requested'])]
    #[TestWith(['approved'])]
    #[TestWith(['rejected'])]
    public function test_non_confirmed_booking_cannot_record_attendance(string $status): void
    {
        $booking = $this->booking(['status' => $status]);
        $staff = $this->assistant($booking->centre);

        $this->expectException(AttendanceTransitionUnavailable::class);
        app(RecordArrival::class)->handle($staff, $booking);
    }

    #[TestWith(['arrival', 'arrival'])]
    #[TestWith(['arrival', 'no-show'])]
    #[TestWith(['no-show', 'arrival'])]
    #[TestWith(['no-show', 'no-show'])]
    #[TestWith(['no-show', 'complete'])]
    #[TestWith(['complete', 'complete'])]
    #[TestWith(['complete', 'arrival'])]
    #[TestWith(['complete', 'no-show'])]
    public function test_repeats_and_terminal_cross_transitions_preserve_first_history(string $first, string $second): void
    {
        $booking = $this->booking();
        $staff = $this->assistant($booking->centre);
        if ($first === 'complete') {
            app(RecordArrival::class)->handle($staff, $booking);
        }
        if ($first !== 'arrival') {
            $this->travelTo(CarbonImmutable::parse('2026-10-05 18:10:00', config('app.timezone')));
        }
        $this->action($first)->handle($staff, $booking);
        if ($second === 'no-show') {
            $this->travelTo(CarbonImmutable::parse('2026-10-05 18:10:00', config('app.timezone')));
        }
        $before = $booking->fresh()->getRawOriginal();
        $count = $booking->activities()->count();

        try {
            $this->action($second)->handle($staff, $booking);
            $this->fail('Repeated or conflicting action must fail.');
        } catch (AttendanceTransitionUnavailable) {
            $this->assertSame($before, $booking->fresh()->getRawOriginal());
            $this->assertSame($count, $booking->activities()->count());
        }
    }

    public function test_audit_failure_rolls_back_attendance_and_history(): void
    {
        $booking = $this->booking();
        $staff = $this->assistant($booking->centre);
        Event::listen('eloquent.created: '.Activity::class, function (Activity $activity): void {
            if ($activity->event === 'booking.arrived') {
                throw new RuntimeException('Attendance audit failure');
            }
        });

        try {
            app(RecordArrival::class)->handle($staff, $booking);
            $this->fail('Audit failure must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Attendance audit failure', $exception->getMessage());
            $this->assertSame(AttendanceState::Expected, $booking->fresh()->attendance_state);
            $this->assertNull($booking->fresh()->arrived_at);
            $this->assertSame(0, $booking->activities()->count());
        }
    }

    public function test_expected_booking_cannot_be_completed_without_arrival(): void
    {
        $booking = $this->booking();
        $staff = $this->assistant($booking->centre);
        $this->travelTo(CarbonImmutable::parse('2026-10-05 18:10:00', config('app.timezone')));

        $this->expectException(AttendanceTransitionUnavailable::class);
        app(CompleteBooking::class)->handle($staff, $booking);
    }

    public function test_loaded_roles_cannot_hide_revocation(): void
    {
        $booking = $this->booking();
        $staff = $this->assistant($booking->centre);
        $staff->load('roles.permissions');
        User::query()->findOrFail($staff->id)->removeRole('leisure-assistant');

        $this->expectException(AuthorizationException::class);
        app(RecordArrival::class)->handle($staff, $booking);
    }

    public function test_unsaved_actor_cannot_impersonate_staff_by_copying_an_identifier(): void
    {
        $booking = $this->booking();
        $staff = $this->assistant($booking->centre);
        $forged = User::factory()->make();
        $forged->id = $staff->id;

        $this->expectException(AuthorizationException::class);
        app(RecordArrival::class)->handle($forged, $booking);
    }

    public function test_arrival_during_previous_calendar_day_setup_and_completion_next_day_cleanup(): void
    {
        config(['app.timezone' => 'Europe/London']);
        $booking = $this->booking(['starts_at' => '2026-10-26 00:05:00', 'ends_at' => '2026-10-26 23:55:00']);
        $staff = $this->assistant($booking->centre);
        $this->travelTo(CarbonImmutable::parse('2026-10-25 23:50:00', 'Europe/London'));

        $arrived = app(RecordArrival::class)->handle($staff, $booking);

        $this->assertSame('2026-10-25 23:50:00', $arrived->arrived_at->toDateTimeString());
        $this->travelTo(CarbonImmutable::parse('2026-10-27 00:05:00', 'Europe/London'));
        $completed = app(CompleteBooking::class)->handle($staff, $booking);
        $this->assertSame('2026-10-27 00:05:00', $completed->completed_at->toDateTimeString());
    }

    public function test_recurring_occurrence_attendance_does_not_change_other_occurrence(): void
    {
        $first = $this->booking();
        $series = BookingSeries::factory()->create(['resource_id' => $first->resource_id, 'customer_id' => $first->customer_id]);
        $first->update(['booking_series_id' => $series->id, 'occurrence_index' => 1]);
        $second = $this->booking(['resource_id' => $first->resource_id, 'customer_id' => $first->customer_id, 'booking_series_id' => $series->id, 'occurrence_index' => 2]);

        app(RecordArrival::class)->handle($this->assistant($first->centre), $first);

        $this->assertSame(AttendanceState::Expected, $second->fresh()->attendance_state);
        $this->assertNull($second->fresh()->arrived_at);
        $this->assertSame(0, $second->activities()->count());
    }

    /** @param array<string, mixed> $attributes */
    private function booking(array $attributes = []): Booking
    {
        $booking = Booking::factory()->create(['starts_at' => '2026-10-05 17:00:00', 'ends_at' => '2026-10-05 18:00:00', 'status' => BookingStatus::Confirmed, 'financial_status' => FinancialStatus::Paid, ...$attributes]);
        $booking->resource->update(['setup_minutes' => 15, 'cleanup_minutes' => 10]);

        return $booking;
    }

    private function assistant(Centre $centre): User
    {
        $staff = User::factory()->create();
        $staff->assignRole('leisure-assistant');
        $staff->assignedCentres()->attach($centre);

        return $staff;
    }

    private function action(string $name): RecordArrival|RecordNoShow|CompleteBooking
    {
        return app(match ($name) {
            'arrival' => RecordArrival::class, 'no-show' => RecordNoShow::class, 'complete' => CompleteBooking::class
        });
    }
}
