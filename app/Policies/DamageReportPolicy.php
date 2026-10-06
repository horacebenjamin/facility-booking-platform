<?php

namespace App\Policies;

use App\Models\Centre;
use App\Models\DamageReport;
use App\Models\User;

class DamageReportPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasCapability($user) && $user->assignedCentres()->exists();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DamageReport $damageReport): bool
    {
        return $this->hasCapability($user) && $user->isAssignedToCentre($damageReport->centre);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, ?Centre $centre = null): bool
    {
        return $this->hasCapability($user)
            && ($centre === null || $user->isAssignedToCentre($centre));
    }

    public function review(User $user, DamageReport $damageReport): bool
    {
        return $user->exists
            && $user->hasRole('manager')
            && $user->can('incidents.manage')
            && $user->isAssignedToCentre($damageReport->centre);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DamageReport $damageReport): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DamageReport $damageReport): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DamageReport $damageReport): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DamageReport $damageReport): bool
    {
        return false;
    }

    private function hasCapability(User $user): bool
    {
        return $user->exists
            && ($user->hasRole('manager') || $user->hasRole('leisure-assistant'))
            && $user->can('incidents.manage');
    }
}
