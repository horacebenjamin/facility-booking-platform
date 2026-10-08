<?php

namespace Tests\Feature\Filament;

use App\Actions\RecordArrival;
use App\Actions\RecordNoShow;
use App\Enums\AttendanceState;
use App\Enums\BookingStatus;
use App\Filament\Operations\Pages\TodaySchedule;
use App\Models\Booking;
use App\Models\Centre;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceScheduleTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemRoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-04 16:45:00', config('app.timezone')));
    }

    public function test_arrival_requires_confirmation_and_refreshes_authoritative_attendance(): void
    {
        $booking = $this->booking();
        $staff = $this->assistant($booking->centre);
        $this->actingAs($staff);
        $page = Livewire::test(TodaySchedule::class)->assertSee('Booking arrived')
            ->assertDontSee('Mark as no-show')->assertDontSee('Complete booking')
            ->mountAction(TestAction::make('recordArrival')->arguments(['booking' => $booking->id]))
            ->assertActionMounted(TestAction::make('recordArrival')->arguments(['booking' => $booking->id]))
            ->assertMountedActionModalSee([$booking->reference, $booking->facility->name, $booking->resource->name, 'financial obligations']);

        $this->assertSame(AttendanceState::Expected, $booking->fresh()->attendance_state);
        $page->call('unmountAction');
        $this->assertSame(AttendanceState::Expected, $booking->fresh()->attendance_state);
        $page->mountAction(TestAction::make('recordArrival')->arguments(['booking' => $booking->id]));
        $page->callMountedAction()->assertSee('Attendance: Arrived')->assertSee('Arrived 04 Oct 16:45')
            ->assertDontSee('Booking arrived');
        $this->assertSame(AttendanceState::Arrived, $booking->fresh()->attendance_state);
    }

    public function test_attendance_confirmation_displays_the_booking_in_london_time(): void
    {
        $booking = $this->booking();
        $booking->update([
            'starts_at' => '2026-10-05 17:00:00',
            'ends_at' => '2026-10-05 18:00:00',
        ]);
        $this->actingAs($this->assistant($booking->centre));

        Livewire::test(TodaySchedule::class)
            ->mountAction(TestAction::make('recordArrival')->arguments(['booking' => $booking->id]))
            ->assertMountedActionModalSee('5 Oct 18:00–19:00');
    }

    public function test_completion_is_offered_after_cleanup_and_retains_full_day_session(): void
    {
        $booking = $this->booking();
        $staff = $this->assistant($booking->centre);
        app(RecordArrival::class)->handle($staff, $booking);
        $this->actingAs($staff);
        $page = Livewire::test(TodaySchedule::class)->assertDontSee('Complete booking');
        $this->travelTo(CarbonImmutable::parse('2026-10-04 18:10:00', config('app.timezone')));

        $page->call('refreshSchedule')->assertSee('Complete booking')
            ->mountAction(TestAction::make('completeBooking')->arguments(['booking' => $booking->id]))
            ->assertActionMounted(TestAction::make('completeBooking')->arguments(['booking' => $booking->id]))
            ->assertMountedActionModalSee([$booking->reference, $booking->facility->name, $booking->resource->name, 'cannot be undone here', 'Payment obligations remain unchanged'])
            ->callMountedAction()->assertSee('Attendance: Completed')->assertSee($booking->reference)
            ->assertSee('Completed 04 Oct 18:10');
        $this->assertSame(AttendanceState::Completed, $booking->fresh()->attendance_state);
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_no_show_confirmation_records_attendance_without_changing_lifecycle(): void
    {
        $booking = $this->booking();
        $staff = $this->assistant($booking->centre);
        $this->actingAs($staff);
        $page = Livewire::test(TodaySchedule::class)->assertDontSee('Mark as no-show');
        $this->travelTo(CarbonImmutable::parse('2026-10-04 18:00:00', config('app.timezone')));

        $page->call('refreshSchedule')->assertSee('Mark as no-show')
            ->mountAction(TestAction::make('recordNoShow')->arguments(['booking' => $booking->id]))
            ->assertActionMounted(TestAction::make('recordNoShow')->arguments(['booking' => $booking->id]))
            ->assertMountedActionModalSee([$booking->reference, $booking->facility->name, $booking->resource->name, 'did not attend', 'cannot be undone here', 'Payment obligations remain unchanged'])
            ->callMountedAction()->assertSee('Attendance: No-show')->assertSee('No-show recorded 04 Oct 18:00');
        $this->assertSame(AttendanceState::NoShow, $booking->fresh()->attendance_state);
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_stale_arrival_confirmation_reports_conflict_and_preserves_original_timestamp(): void
    {
        $booking = $this->booking();
        $staff = $this->assistant($booking->centre);
        $this->actingAs($staff);
        $page = Livewire::test(TodaySchedule::class)
            ->mountAction(TestAction::make('recordArrival')->arguments(['booking' => $booking->id]));
        app(RecordArrival::class)->handle($staff, $booking);
        $original = $booking->fresh()->arrived_at->toDateTimeString();
        $this->travel(1)->minutes();

        $page->callMountedAction()->assertSee('Attendance: Arrived')->assertNotified();
        $this->assertSame($original, $booking->fresh()->arrived_at->toDateTimeString());
    }

    public function test_forged_cross_centre_action_is_denied_even_when_both_centres_are_assigned(): void
    {
        $booking = $this->booking();
        $foreign = $this->booking();
        $staff = $this->assistant($booking->centre);
        $staff->assignedCentres()->attach($foreign->centre);
        $this->actingAs($staff);

        Livewire::test(TodaySchedule::class)->set('centreId', $booking->centre_id)
            ->mountAction(TestAction::make('recordArrival')->arguments(['booking' => $foreign->id]))
            ->assertNotFound();
        $this->assertSame(AttendanceState::Expected, $foreign->fresh()->attendance_state);
    }

    public function test_switching_centre_after_confirmation_does_not_authorise_old_booking(): void
    {
        $booking = $this->booking();
        $other = $this->booking();
        $staff = $this->assistant($booking->centre);
        $staff->assignedCentres()->attach($other->centre);
        $this->actingAs($staff);

        Livewire::test(TodaySchedule::class)->set('centreId', $booking->centre_id)
            ->mountAction(TestAction::make('recordArrival')->arguments(['booking' => $booking->id]))
            ->set('centreId', $other->centre_id)->assertNotFound();
        $this->assertSame(AttendanceState::Expected, $booking->fresh()->attendance_state);
    }

    public function test_without_attendance_permission_schedule_is_readable_but_action_is_denied(): void
    {
        $booking = $this->booking();
        $staff = $this->assistant($booking->centre);
        $staff->roles()->firstOrFail()->revokePermissionTo('attendance.manage');
        $this->actingAs($staff);

        Livewire::test(TodaySchedule::class)->assertSee($booking->reference)->assertSee('Attendance: Expected')
            ->assertDontSee('Booking arrived')->assertDontSee('Mark as no-show')->assertDontSee('Complete booking')
            ->mountAction(TestAction::make('recordArrival')->arguments(['booking' => $booking->id]))
            ->assertForbidden();
    }

    public function test_assignment_revoked_after_confirmation_is_denied_without_recording_arrival(): void
    {
        $booking = $this->booking();
        $staff = $this->assistant($booking->centre);
        $this->actingAs($staff);
        $page = Livewire::test(TodaySchedule::class)
            ->mountAction(TestAction::make('recordArrival')->arguments(['booking' => $booking->id]));
        $staff->assignedCentres()->detach($booking->centre);

        $page->callMountedAction()->assertForbidden();
        $this->assertSame(AttendanceState::Expected, $booking->fresh()->attendance_state);
    }

    public function test_refresh_shows_external_no_show_without_repeating_action(): void
    {
        $booking = $this->booking();
        $staff = $this->assistant($booking->centre);
        $this->travelTo(CarbonImmutable::parse('2026-10-04 18:00:00', config('app.timezone')));
        $this->actingAs($staff);
        $page = Livewire::test(TodaySchedule::class)->assertSee('Mark as no-show');
        app(RecordNoShow::class)->handle($staff, $booking);
        $original = $booking->fresh()->no_show_recorded_at->toDateTimeString();

        $page->call('refreshSchedule')->call('refreshSchedule')->assertSee('Attendance: No-show')
            ->assertDontSee('Mark as no-show');
        $this->assertSame($original, $booking->fresh()->no_show_recorded_at->toDateTimeString());
    }

    private function booking(): Booking
    {
        $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed, 'starts_at' => '2026-10-04 17:00:00', 'ends_at' => '2026-10-04 18:00:00']);
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
}
