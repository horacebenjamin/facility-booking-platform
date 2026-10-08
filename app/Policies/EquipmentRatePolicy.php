<?php

namespace App\Policies;

use App\Models\EquipmentRate;
use App\Models\User;

class EquipmentRatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('manager') && $user->can('pricing.manage');
    }

    public function view(User $user, EquipmentRate $equipmentRate): bool
    {
        return $user->hasRole('manager') && $user->can('pricing.manage')
            && $user->isAssignedToCentre($equipmentRate->equipment->centre);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('manager') && $user->can('pricing.manage') && $user->assignedCentres()->exists();
    }

    public function update(User $user, EquipmentRate $equipmentRate): bool
    {
        return $this->view($user, $equipmentRate);
    }

    public function delete(User $user, EquipmentRate $equipmentRate): bool
    {
        return $this->view($user, $equipmentRate);
    }
}
