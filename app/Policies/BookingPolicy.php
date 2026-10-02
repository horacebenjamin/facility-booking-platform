<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bookings.view') && $user->assignedCentres()->exists();
    }

    public function view(User $user, Booking $booking): bool
    {
        return $user->can('bookings.view') && $user->isAssignedToCentre($booking->centre);
    }

    public function approve(User $user, Booking $booking): bool
    {
        return $user->can('bookings.approve') && $user->isAssignedToCentre($booking->centre);
    }

    public function reject(User $user, Booking $booking): bool
    {
        return $user->can('bookings.approve') && $user->isAssignedToCentre($booking->centre);
    }
}
