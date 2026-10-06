<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function manageAttendance(User $user, Booking $booking): bool
    {
        return $user->exists
            && $user->hasRole('leisure-assistant')
            && $user->can('bookings.view')
            && $user->can('attendance.manage')
            && $user->isAssignedToCentre($booking->centre);
    }

    public function manageInvoiceTerms(User $user, Booking $booking): bool
    {
        return $user->can('invoices.manage') && $user->isAssignedToCentre($booking->centre);
    }

    public function viewPayment(User $user, Booking $booking): bool
    {
        return $user->can('bookings.view') && $user->id === $booking->customer_id;
    }

    public function viewCustomer(User $user, Booking $booking): bool
    {
        return $user->hasRole('customer')
            && $user->can('bookings.view')
            && $user->id === $booking->customer_id;
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $user->hasRole('customer')
            && $user->can('bookings.cancel')
            && $user->id === $booking->customer_id;
    }

    public function amend(User $user, Booking $booking): bool
    {
        return $user->hasRole('customer')
            && $user->can('bookings.amend')
            && $user->id === $booking->customer_id;
    }

    public function pay(User $user, Booking $booking): bool
    {
        return $user->can('payments.initiate') && $user->id === $booking->customer_id;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('bookings.view') && $user->assignedCentres()->exists();
    }

    public function createManual(User $user): bool
    {
        return $user->hasRole('manager')
            && $user->can('bookings.create')
            && $user->assignedCentres()->exists();
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
