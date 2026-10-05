<?php

namespace App\Services;

use App\Enums\AttendanceState;
use App\Enums\BookingStatus;
use App\Exceptions\AttendanceTransitionUnavailable;
use App\Models\Booking;
use Carbon\CarbonInterface;

class AttendanceEligibility
{
    public function __construct(private OperationalOccupancyCalculator $occupancyCalculator) {}

    public function assertTransition(Booking $booking, AttendanceState $target, CarbonInterface $at, ?OperationalOccupancyPeriod $period = null): void
    {
        if (! in_array($target, $this->availableTransitions($booking, $at, $period), true)) {
            throw new AttendanceTransitionUnavailable('This attendance action is no longer available for this booking. Refresh the schedule.');
        }
    }

    /** @return list<AttendanceState> */
    public function availableTransitions(Booking $booking, CarbonInterface $at, ?OperationalOccupancyPeriod $period = null): array
    {
        if ($booking->status !== BookingStatus::Confirmed) {
            return [];
        }

        $period ??= $this->occupancyCalculator->calculate($booking->resource, $booking->starts_at, $booking->ends_at);

        if ($period === null) {
            return [];
        }

        return match (true) {
            $booking->attendance_state === AttendanceState::Expected && $at->gte($booking->ends_at) => [AttendanceState::NoShow],
            $booking->attendance_state === AttendanceState::Expected && $at->gte($period->startsAt) && $at->lt($booking->ends_at) => [AttendanceState::Arrived],
            $booking->attendance_state === AttendanceState::Arrived && $at->gte($period->endsAt) => [AttendanceState::Completed],
            default => [],
        };
    }
}
