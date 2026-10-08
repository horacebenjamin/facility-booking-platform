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
        return $user->hasRole('manager') && $user->can('invoices.manage') && $user->isAssignedToCentre($booking->centre);
    }

    public function viewPayment(User $user, Booking $booking): bool
    {
        return $user->hasRole('customer') && $user->can('bookings.view') && $this->canManageFinance($user, $booking);
    }

    public function viewCustomer(User $user, Booking $booking): bool
    {
        return $user->hasRole('customer')
            && $user->can('bookings.view')
            && $this->canViewForCustomer($user, $booking);
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $user->hasRole('customer')
            && $user->can('bookings.cancel')
            && $this->canManageForCustomer($user, $booking);
    }

    public function amend(User $user, Booking $booking): bool
    {
        return $user->hasRole('customer')
            && $user->can('bookings.amend')
            && $this->canManageForCustomer($user, $booking);
    }

    public function pay(User $user, Booking $booking): bool
    {
        return $user->hasRole('customer') && $user->can('payments.initiate') && $this->canManageFinance($user, $booking);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('manager') && $user->can('bookings.view') && $user->assignedCentres()->exists();
    }

    public function createManual(User $user): bool
    {
        return $user->hasRole('manager')
            && $user->can('bookings.create')
            && $user->assignedCentres()->exists();
    }

    public function view(User $user, Booking $booking): bool
    {
        return ($user->hasRole('manager') || $user->hasRole('leisure-assistant'))
            && $user->can('bookings.view')
            && $user->isAssignedToCentre($booking->centre);
    }

    public function approve(User $user, Booking $booking): bool
    {
        return $user->hasRole('manager') && $user->can('bookings.approve') && $user->isAssignedToCentre($booking->centre);
    }

    public function reject(User $user, Booking $booking): bool
    {
        return $user->hasRole('manager') && $user->can('bookings.approve') && $user->isAssignedToCentre($booking->centre);
    }

    private function canViewForCustomer(User $user, Booking $booking): bool
    {
        if ($booking->organisation_id === null) {
            return $user->id === $booking->customer_id;
        }

        return $booking->organisation?->membershipFor($user)?->role->canViewBookings() === true;
    }

    private function canManageForCustomer(User $user, Booking $booking): bool
    {
        if ($booking->organisation_id === null) {
            return $user->id === $booking->customer_id;
        }

        return $booking->organisation?->membershipFor($user)?->role->canManageBookings() === true;
    }

    private function canManageFinance(User $user, Booking $booking): bool
    {
        if ($booking->organisation_id === null) {
            return $user->id === $booking->customer_id;
        }

        return $booking->organisation?->membershipFor($user)?->role->canManageFinance() === true;
    }
}
