<?php

namespace App\Services;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

class OrganisationBookingContext
{
    /** @return Collection<int, OrganisationMembership> */
    public function bookableMemberships(User $user): Collection
    {
        return $user->organisationMemberships()
            ->with('organisation')
            ->whereIn('role', [
                OrganisationRole::Owner->value,
                OrganisationRole::Admin->value,
                OrganisationRole::BookingManager->value,
            ])
            ->orderBy('organisation_id')
            ->get();
    }

    public function resolveBookable(User $user, ?int $organisationId): ?Organisation
    {
        if ($organisationId === null) {
            return null;
        }

        $membership = $this->bookableMemberships($user)->firstWhere('organisation_id', $organisationId);
        abort_if($membership === null, 404);

        return $membership->organisation;
    }

    /**
     * Re-authorise organisation booking creation inside the booking transaction.
     *
     * Must be called after the centre reservation lock so locks are taken in the
     * same centre → customer → organisation order used by approval and invoice terms.
     * The membership is read with a locking read so a concurrently committed removal
     * or role change is always observed.
     */
    public function lockForBooking(User $customer, Organisation $organisation): Organisation
    {
        User::query()->lockForUpdate()->findOrFail($customer->id);
        $organisation = Organisation::query()->lockForUpdate()->findOrFail($organisation->id);
        $membership = OrganisationMembership::query()
            ->whereBelongsTo($organisation)
            ->whereBelongsTo($customer)
            ->lockForUpdate()
            ->first();

        if ($membership?->role->canCreateBookings() !== true) {
            throw new AuthorizationException;
        }

        Gate::forUser($customer)->authorize('createBooking', $organisation);

        return $organisation;
    }
}
