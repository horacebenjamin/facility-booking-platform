<?php

namespace App\Services;

use App\Models\AvailabilityBlock;
use App\Models\AvailabilityBlockBookingImpact;
use App\Models\Centre;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class ClosureAuthorization
{
    public function freshActor(User $actor): User
    {
        if (! $actor->exists || $actor->getKey() === null) {
            throw new AuthorizationException;
        }
        $fresh = User::query()->whereKey($actor->getKey())->first();
        if ($fresh === null) {
            throw new AuthorizationException;
        }

        return $fresh;
    }

    public function authorizeCentre(User $actor, Centre $centre): User
    {
        $actor = $this->freshActor($actor);
        Gate::forUser($actor)->authorize('createScoped', [AvailabilityBlock::class, $centre]);

        return $actor;
    }

    public function authorizeBlock(User $actor, AvailabilityBlock $block, string $ability = 'view'): User
    {
        $actor = $this->freshActor($actor);
        Gate::forUser($actor)->authorize($ability, $block);

        return $actor;
    }

    public function authorizeImpact(User $actor, AvailabilityBlockBookingImpact $impact): User
    {
        $impact = AvailabilityBlockBookingImpact::query()->with(['availabilityBlock', 'booking'])->whereKey($impact->id)->firstOrFail();
        $actor = $this->authorizeBlock($actor, $impact->availabilityBlock);
        if (! $actor->can('bookings.view') || $impact->booking->centre_id !== $impact->availabilityBlock->owningCentre()->id) {
            throw new AuthorizationException;
        }

        return $actor;
    }
}
