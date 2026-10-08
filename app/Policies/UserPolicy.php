<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class UserPolicy
{
    public function view(User $user, User $profile): bool
    {
        return $user->is($profile);
    }

    public function update(User $user, User $profile): bool
    {
        return $user->is($profile);
    }

    public function delete(User $user, User $profile): bool
    {
        return $user->is($profile);
    }

    /**
     * Staff may only create customer accounts as part of the assisted booking workflow.
     */
    public function createCustomer(User $user): bool
    {
        return $user->can('createManual', Booking::class);
    }
}
