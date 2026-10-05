<?php

namespace App\Policies;

use App\Models\AvailabilityBlock;
use App\Models\Centre;
use App\Models\User;

class AvailabilityBlockPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCapability($user) && $user->assignedCentres()->exists();
    }

    public function create(User $user, ?Centre $centre = null): bool
    {
        return $this->hasCapability($user)
            && ($centre === null ? $user->assignedCentres()->exists() : $user->isAssignedToCentre($centre));
    }

    public function createScoped(User $user, Centre $centre): bool
    {
        return $this->create($user, $centre);
    }

    public function view(User $user, AvailabilityBlock $block): bool
    {
        return $this->hasCapability($user) && $user->isAssignedToCentre($block->owningCentre());
    }

    public function end(User $user, AvailabilityBlock $block): bool
    {
        return $this->view($user, $block);
    }

    public function update(User $user, AvailabilityBlock $block): bool
    {
        return false;
    }

    public function delete(User $user, AvailabilityBlock $block): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function hasCapability(User $user): bool
    {
        return $user->exists && $user->hasRole('manager') && $user->can('closures.manage');
    }
}
