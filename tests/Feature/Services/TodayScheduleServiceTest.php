<?php

namespace Tests\Feature\Services;

use App\Actions\CompleteBooking;
use App\Actions\RecordArrival;
use App\Actions\RecordNoShow;
use App\Enums\AttendanceState;
use App\Enums\BookingStatus;
use App\Enums\FinancialStatus;
use App\Models\Booking;
use App\Models\BookingEquipment;
use App\Models\BookingSeries;
use App\Models\Centre;
use App\Models\Resource;
use App\Models\User;
use App\Services\TodayScheduleService;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TodayScheduleServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemRoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-04 16:45:00', config('app.timezone')));
    }

    public function test_only_confirmed_bookings_at_the_selected_centre_are_operational_regardless_of_financial_state(): void
    {
        $confirmed = $this->booking(['financial_status' => FinancialStatus::AwaitingPayment]);
        $staff = $this->assistant($confirmed->centre);
        foreach ([BookingStatus::Requested, BookingStatus::Approved, BookingStatus::Rejected] as $status) {
            $this->booking(['resource_id' => $confirmed->resource_id, 'status' => $status]);
        }
        $this->booking();

        $schedule = app(TodayScheduleService::class)->forDate($staff, $confirmed->centre, '2026-10-04');

        $this->assertSame([$confirmed->id], array_column($schedule->sessions, 'bookingId'));
    }

    #[TestWith(['customer'])]
    #[TestWith(['manager'])]
    public function test_assigned_users_without_operations_access_cannot_query_schedule(string $role): void
    {
        $centre = Centre::factory()->create();
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->assignedCentres()->attach($centre);

        $this->expectException(AuthorizationException::class);

        app(TodayScheduleService::class)->forDate($user, $centre, '2026-10-04');
    }

    public function test_multiple_centre_cover_is_limited_to_current_assignments(): void
    {
        $first = Centre::factory()->create();
        $second = Centre::factory()->create();
        $staff = $this->assistant($first);
        $staff->assignedCentres()->attach($second);
        $service = app(TodayScheduleService::class);

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $service->authorizedCentres($staff)->modelKeys());
        $this->assertSame([], $service->forDate($staff, $second, '2026-10-04')->sessions);
        $staff->load('assignedCentres');
        $staff->assignedCentres()->detach($second);

        $this->expectException(AuthorizationException::class);

        $service->forDate($staff, $second, '2026-10-04');
    }

    public function test_forged_unassigned_centre_is_denied(): void
    {
        $staff = $this->assistant(Centre::factory()->create());
        $foreign = Centre::factory()->create();

        $this->expectException(AuthorizationException::class);

        app(TodayScheduleService::class)->forDate($staff, $foreign, '2026-10-04');
    }

    public function test_operations_role_and_assignment_without_booking_capability_are_insufficient(): void
    {
        $centre = Centre::factory()->create();
        $staff = $this->assistant($centre);
        $staff->roles()->firstOrFail()->revokePermissionTo('bookings.view');

        $this->expectException(AuthorizationException::class);

        app(TodayScheduleService::class)->forDate($staff, $centre, '2026-10-04');
    }

    public function test_non_default_timezone_dst_day_and_long_setup_buffer_use_application_calendar_boundaries(): void
    {
        config(['app.timezone' => 'Europe/London']);
        $this->travelTo(CarbonImmutable::parse('2026-10-25 12:00:00', 'Europe/London'));
        $booking = $this->booking(['starts_at' => '2026-10-27 00:00:00', 'ends_at' => '2026-10-27 01:00:00']);
        $booking->resource->update(['setup_minutes' => 1500]);
        $early = $this->booking(['resource_id' => $booking->resource_id, 'starts_at' => '2026-10-25 02:00:00', 'ends_at' => '2026-10-25 03:00:00']);

        $schedule = app(TodayScheduleService::class)->forDate($this->assistant($booking->centre), $booking->centre, '2026-10-25');

        $this->assertSame([$early->id, $booking->id], array_column($schedule->sessions, 'bookingId'));
        $this->assertSame('2026-10-25 23:00', $schedule->sessions[1]->operationalStartsAt->format('Y-m-d H:i'));
        $this->assertSame('Europe/London', $schedule->refreshedAt->timezoneName);
    }

    public function test_now_includes_setup_and_cleanup_with_half_open_end_and_next_includes_tied_sessions(): void
    {
        $current = $this->booking();
        $current->resource->update(['setup_minutes' => 15, 'cleanup_minutes' => 10]);
        $next = $this->booking(['resource_id' => $current->resource_id, 'starts_at' => '2026-10-04 19:00:00', 'ends_at' => '2026-10-04 20:00:00']);
        $tied = $this->booking(['resource_id' => $current->resource_id, 'starts_at' => '2026-10-04 19:00:00', 'ends_at' => '2026-10-04 20:00:00']);
        $this->booking(['resource_id' => $current->resource_id, 'starts_at' => '2026-10-04 21:00:00', 'ends_at' => '2026-10-04 22:00:00']);
        $staff = $this->assistant($current->centre);
        $service = app(TodayScheduleService::class);

        $schedule = $service->forDate($staff, $current->centre, '2026-10-04');

        $this->assertSame([$current->id], array_column($schedule->now, 'bookingId'));
        $this->assertSame([$next->id, $tied->id], array_column($schedule->next, 'bookingId'));
        $this->assertSame('16:45', $schedule->sessions[0]->operationalStartsAt->format('H:i'));
        $this->assertSame('18:10', $schedule->sessions[0]->operationalEndsAt->format('H:i'));
        $this->assertSame('Setup', $schedule->sessions[0]->phase($schedule->refreshedAt));
        $this->assertSame('Session', $schedule->sessions[0]->phase(CarbonImmutable::parse('2026-10-04 17:00:00', config('app.timezone'))));
        $this->assertSame('Cleanup', $schedule->sessions[0]->phase(CarbonImmutable::parse('2026-10-04 18:00:00', config('app.timezone'))));
        $this->assertSame('Ended', $schedule->sessions[0]->phase(CarbonImmutable::parse('2026-10-04 18:10:00', config('app.timezone'))));

        $this->travelTo(CarbonImmutable::parse('2026-10-04 18:09:59', config('app.timezone')));
        $this->assertSame([$current->id], array_column($service->forDate($staff, $current->centre, '2026-10-04')->now, 'bookingId'));
        $this->travelTo(CarbonImmutable::parse('2026-10-04 18:10:00', config('app.timezone')));
        $this->assertSame([], $service->forDate($staff, $current->centre, '2026-10-04')->now);
    }

    public function test_operational_overlap_crossing_midnight_is_included_and_exact_day_edges_are_excluded(): void
    {
        $cleanup = $this->booking(['starts_at' => '2026-10-03 23:00:00', 'ends_at' => '2026-10-03 23:55:00']);
        $cleanup->resource->update(['cleanup_minutes' => 10]);
        $setup = $this->booking(['resource_id' => $cleanup->resource_id, 'starts_at' => '2026-10-05 00:05:00', 'ends_at' => '2026-10-05 01:00:00']);
        $setup->resource->update(['setup_minutes' => 10]);
        $resource = Resource::factory()->create(['facility_id' => $cleanup->facility_id]);
        $this->booking(['resource_id' => $resource->id, 'starts_at' => '2026-10-03 23:00:00', 'ends_at' => '2026-10-04 00:00:00']);
        $this->booking(['resource_id' => $resource->id, 'starts_at' => '2026-10-05 00:00:00', 'ends_at' => '2026-10-05 01:00:00']);

        $schedule = app(TodayScheduleService::class)->forDate($this->assistant($cleanup->centre), $cleanup->centre, '2026-10-04');

        $this->assertSame([$cleanup->id, $setup->id], array_column($schedule->sessions, 'bookingId'));
    }

    public function test_occurrences_keep_independent_identity_and_schedule_is_chronological(): void
    {
        $later = $this->booking();
        $series = BookingSeries::factory()->create(['resource_id' => $later->resource_id, 'customer_id' => $later->customer_id]);
        $later->update(['booking_series_id' => $series->id, 'occurrence_index' => 2]);
        $earlier = $this->booking(['resource_id' => $later->resource_id, 'customer_id' => $series->customer_id, 'booking_series_id' => $series->id, 'occurrence_index' => 1, 'starts_at' => '2026-10-04 10:00:00', 'ends_at' => '2026-10-04 11:00:00']);

        $schedule = app(TodayScheduleService::class)->forDate($this->assistant($later->centre), $later->centre, '2026-10-04');

        $this->assertSame([$earlier->id, $later->id], array_column($schedule->sessions, 'bookingId'));
        $this->assertSame([$earlier->reference, $later->reference], array_column($schedule->sessions, 'reference'));
        $this->assertSame([1, 2], array_column($schedule->sessions, 'occurrenceIndex'));
        $this->assertSame($series->identifier, $schedule->sessions[0]->seriesIdentifier);
    }

    public function test_repeated_queries_reflect_authoritative_resource_equipment_and_booking_changes_without_writes(): void
    {
        $booking = $this->booking();
        $request = BookingEquipment::factory()->create(['booking_id' => $booking->id, 'requested_quantity' => 3]);
        $staff = $this->assistant($booking->centre);
        $service = app(TodayScheduleService::class);
        $session = $service->forDate($staff, $booking->centre, '2026-10-04')->sessions[0];
        $this->assertSame([['name' => $request->equipment->name, 'quantity' => 3]], $session->equipment);
        $this->assertSame($booking->facility->name, $session->facilityName);
        $this->assertSame($booking->resource->name, $session->resourceName);
        $this->assertSame($booking->customer->name, $session->customerName);
        $this->assertSame(['bookingId', 'reference', 'customerName', 'facilityName', 'resourceName', 'startsAt', 'endsAt', 'operationalStartsAt', 'operationalEndsAt', 'seriesIdentifier', 'occurrenceIndex', 'occurrenceCount', 'equipment', 'attendanceState', 'arrivedAt', 'noShowRecordedAt', 'completedAt', 'availableTransitions'], array_keys(get_object_vars($session)));
        $request->update(['requested_quantity' => 5]);
        $request->equipment->update(['name' => 'Updated equipment']);
        $booking->resource->update(['setup_minutes' => 20, 'name' => 'Updated resource']);

        $updated = $service->forDate($staff, $booking->centre, '2026-10-04')->sessions[0];

        $this->assertSame([['name' => 'Updated equipment', 'quantity' => 5]], $updated->equipment);
        $this->assertSame('Updated resource', $updated->resourceName);
        $this->assertSame('16:40', $updated->operationalStartsAt->format('H:i'));
        $booking->update(['status' => BookingStatus::Rejected]);
        $this->assertSame([], $service->forDate($staff, $booking->centre, '2026-10-04')->sessions);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_equipment', 1);
        $this->assertDatabaseCount('allocation_occupancies', 0);
    }

    #[TestWith(['2026-02-30'])]
    #[TestWith(['tomorrow'])]
    #[TestWith(['2026-10-04 00:00:00'])]
    public function test_invalid_calendar_date_is_rejected(string $date): void
    {
        $centre = Centre::factory()->create();
        $staff = $this->assistant($centre);

        $this->expectException(ValidationException::class);

        app(TodayScheduleService::class)->forDate($staff, $centre, $date);
    }

    public function test_query_count_does_not_grow_with_number_of_sessions(): void
    {
        $booking = $this->booking();
        BookingEquipment::factory()->create(['booking_id' => $booking->id]);
        $staff = $this->assistant($booking->centre);
        $service = app(TodayScheduleService::class);
        $service->forDate($staff, $booking->centre, '2026-10-04');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $service->forDate($staff, $booking->centre, '2026-10-04');
        $singleCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $additional = Booking::factory()->count(4)->create(['resource_id' => $booking->resource_id, 'starts_at' => '2026-10-04 17:00:00', 'ends_at' => '2026-10-04 18:00:00', 'status' => BookingStatus::Confirmed]);
        foreach ($additional as $session) {
            BookingEquipment::factory()->create(['booking_id' => $session->id]);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();

        $schedule = $service->forDate($staff, $booking->centre, '2026-10-04');
        $multipleCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(5, $schedule->sessions);
        $this->assertSame($singleCount, $multipleCount);
    }

    public function test_schedule_reflects_attendance_and_retains_terminal_outcomes_in_the_full_day(): void
    {
        $booking = $this->booking();
        $booking->resource->update(['setup_minutes' => 15, 'cleanup_minutes' => 10]);
        $other = $this->booking(['resource_id' => $booking->resource_id]);
        $staff = $this->assistant($booking->centre);
        $service = app(TodayScheduleService::class);
        $before = $service->forDate($staff, $booking->centre, '2026-10-04');
        $this->assertSame([AttendanceState::Arrived], $before->sessions[0]->availableTransitions);

        app(RecordArrival::class)->handle($staff, $booking);
        $arrived = $service->forDate($staff, $booking->centre, '2026-10-04');
        $this->assertSame(AttendanceState::Arrived, $arrived->sessions[0]->attendanceState);
        $this->assertSame('16:45', $arrived->sessions[0]->arrivedAt?->format('H:i'));
        $this->assertSame([], $arrived->sessions[0]->availableTransitions);

        $this->travelTo(CarbonImmutable::parse('2026-10-04 18:00:00', config('app.timezone')));
        app(RecordNoShow::class)->handle($staff, $other);
        $noShow = $service->forDate($staff, $booking->centre, '2026-10-04');
        $this->assertSame([$booking->id], array_column($noShow->now, 'bookingId'));
        $this->assertSame(AttendanceState::NoShow, $noShow->sessions[1]->attendanceState);
        $this->assertSame([], $noShow->sessions[1]->availableTransitions);

        $this->travelTo(CarbonImmutable::parse('2026-10-04 18:10:00', config('app.timezone')));
        app(CompleteBooking::class)->handle($staff, $booking);
        $completed = $service->forDate($staff, $booking->centre, '2026-10-04');
        $this->assertSame([$booking->id, $other->id], array_column($completed->sessions, 'bookingId'));
        $this->assertSame(AttendanceState::Completed, $completed->sessions[0]->attendanceState);
        $this->assertSame([], $completed->now);
        $this->assertSame([], $completed->sessions[0]->availableTransitions);
        $this->assertDatabaseCount('bookings', 2);
        $this->assertDatabaseCount('activity_log', 3);
    }

    public function test_schedule_without_attendance_permission_still_displays_sessions_but_offers_no_mutations(): void
    {
        $booking = $this->booking();
        $booking->resource->update(['setup_minutes' => 15]);
        $staff = $this->assistant($booking->centre);
        $staff->roles()->firstOrFail()->revokePermissionTo('attendance.manage');

        $session = app(TodayScheduleService::class)->forDate($staff, $booking->centre, '2026-10-04')->sessions[0];

        $this->assertSame(AttendanceState::Expected, $session->attendanceState);
        $this->assertSame([], $session->availableTransitions);
    }

    /** @param array<string, mixed> $attributes */
    private function booking(array $attributes = []): Booking
    {
        return Booking::factory()->create(['starts_at' => '2026-10-04 17:00:00', 'ends_at' => '2026-10-04 18:00:00', 'status' => BookingStatus::Confirmed, ...$attributes]);
    }

    private function assistant(Centre $centre): User
    {
        $staff = User::factory()->create();
        $staff->assignRole('leisure-assistant');
        $staff->assignedCentres()->attach($centre);

        return $staff;
    }
}
