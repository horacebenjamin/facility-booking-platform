<?php

namespace App\Policies;

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
}
