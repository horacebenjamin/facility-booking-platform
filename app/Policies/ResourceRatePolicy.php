<?php

namespace App\Policies;

use App\Models\ResourceRate;
use App\Models\User;

class ResourceRatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('manager') && $user->can('pricing.manage');
    }

    public function view(User $user, ResourceRate $resourceRate): bool
    {
        return $user->hasRole('manager') && $user->can('pricing.manage')
            && $user->isAssignedToCentre($resourceRate->resource->facility->centre);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('manager') && $user->can('pricing.manage') && $user->assignedCentres()->exists();
    }

    public function update(User $user, ResourceRate $resourceRate): bool
    {
        return $this->view($user, $resourceRate);
    }

    public function delete(User $user, ResourceRate $resourceRate): bool
    {
        return $this->view($user, $resourceRate);
    }
}
