<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\Organisation;
use App\Models\User;

class OrganisationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('customer');
    }

    public function view(User $user, Organisation $organisation): bool
    {
        return $user->hasRole('customer') && $user->organisationMembership($organisation) !== null;
    }

    public function viewFinance(User $user, Organisation $organisation): bool
    {
        return $user->hasRole('customer')
            && $user->can('bookings.view')
            && $user->organisationMembership($organisation)?->role->canManageFinance() === true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('customer');
    }

    public function manageMembers(User $user, Organisation $organisation): bool
    {
        return $user->hasRole('customer')
            && $user->organisationMembership($organisation)?->role->canManageMembers() === true;
    }

    public function createBooking(User $user, Organisation $organisation): bool
    {
        return $user->hasRole('customer')
            && $user->can('bookings.create')
            && $user->organisationMembership($organisation)?->role->canCreateBookings() === true;
    }

    /**
     * Staff creating an organisation for a customer they onboard during an assisted booking.
     * Requires the dedicated onboarding capability in addition to assisted-booking access;
     * self-service organisation abilities above remain customer-only.
     */
    public function createForAssistedCustomer(User $user): bool
    {
        return $user->hasRole('manager')
            && $user->can('organisations.onboard')
            && $user->can('createManual', Booking::class);
    }

    /**
     * Staff adding a customer they onboard during an assisted booking to an existing organisation.
     */
    public function addAssistedCustomer(User $user, Organisation $organisation): bool
    {
        return $this->createForAssistedCustomer($user);
    }
}
