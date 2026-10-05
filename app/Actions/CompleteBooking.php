<?php

namespace App\Actions;

use App\Enums\AttendanceState;
use App\Models\Booking;
use App\Models\User;

class CompleteBooking
{
    public function __construct(private RecordAttendance $recordAttendance) {}

    public function handle(User $actor, Booking $booking): Booking
    {
        return $this->recordAttendance->handle($actor, $booking, AttendanceState::Completed);
    }
}
