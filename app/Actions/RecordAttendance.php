<?php

namespace App\Actions;

use App\Enums\AttendanceState;
use App\Models\Booking;
use App\Models\User;
use App\Services\AttendanceEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RecordAttendance
{
    public function __construct(private AttendanceEligibility $eligibility) {}

    public function handle(User $actor, Booking $booking, AttendanceState $target): Booking
    {
        $actor = $this->freshActor($actor);
        Gate::forUser($actor)->authorize('manageAttendance', $booking);

        return DB::transaction(function () use ($actor, $booking, $target): Booking {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $booking->load(['centre', 'resource']);
            $actor = $this->freshActor($actor);
            Gate::forUser($actor)->authorize('manageAttendance', $booking);
            $recordedAt = CarbonImmutable::now(config('app.timezone'));
            $this->eligibility->assertTransition($booking, $target, $recordedAt);
            $oldState = $booking->attendance_state;
            $timestamp = match ($target) {
                AttendanceState::Arrived => 'arrived_at',
                AttendanceState::NoShow => 'no_show_recorded_at',
                AttendanceState::Completed => 'completed_at',
                AttendanceState::Expected => throw new \LogicException('Expected is not a recordable attendance action.'),
            };
            $event = match ($target) {
                AttendanceState::Arrived => 'booking.arrived',
                AttendanceState::NoShow => 'booking.no_show',
                AttendanceState::Completed => 'booking.completed',
            };
            $booking->forceFill(['attendance_state' => $target, $timestamp => $recordedAt])->save();
            activity('booking')->performedOn($booking)->causedBy($actor)->event($event)
                ->withProperties([
                    'old_attendance_state' => $oldState->value,
                    'new_attendance_state' => $target->value,
                    'recorded_at' => $recordedAt->toIso8601String(),
                ])->log('Booking attendance recorded: '.$target->label());

            return $booking;
        });
    }

    private function freshActor(User $actor): User
    {
        if (! $actor->exists || $actor->getKey() === null) {
            throw new AuthorizationException;
        }

        $freshActor = User::query()->whereKey($actor->getKey())->first();

        if ($freshActor === null) {
            throw new AuthorizationException;
        }

        return $freshActor;
    }
}
