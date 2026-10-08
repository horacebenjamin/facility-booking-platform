<?php

namespace App\Actions;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AddOrganisationMember
{
    public const string DUPLICATE_MEMBERSHIP_MESSAGE = 'This customer is already a member of the organisation.';

    public function handle(User $actor, Organisation $organisation, string $email, OrganisationRole $role): OrganisationMembership
    {
        Gate::forUser($actor)->authorize('manageMembers', $organisation);

        return DB::transaction(function () use ($actor, $organisation, $email, $role): OrganisationMembership {
            Organisation::query()->lockForUpdate()->findOrFail($organisation->id);
            $actorMembership = OrganisationMembership::query()
                ->whereBelongsTo($organisation)
                ->whereBelongsTo($actor)
                ->lockForUpdate()
                ->first();

            if ($actorMembership?->role->canManageMembers() !== true
                || ($actorMembership->role === OrganisationRole::Admin && $role === OrganisationRole::Owner)) {
                throw new AuthorizationException;
            }

            $user = User::query()->where('email', trim($email))->lockForUpdate()->first();

            if ($user === null || ! $user->hasRole('customer')) {
                throw ValidationException::withMessages(['email' => 'No customer account was found for this email address.']);
            }

            return $this->createMembership($actor, $organisation, $user, $role, 'email', [
                'actor_organisation_role' => $actorMembership->role->value,
            ]);
        });
    }

    /**
     * Adds a customer onboarded by staff during an assisted booking to an existing
     * organisation. Ownership cannot be granted this way.
     */
    public function handleForAssistedCustomer(User $staff, Organisation $organisation, User $customer, OrganisationRole $role): OrganisationMembership
    {
        Gate::forUser($staff)->authorize('addAssistedCustomer', $organisation);

        if (! in_array($role, OrganisationRole::staffAssignable(), true)) {
            throw ValidationException::withMessages(['organisation_role' => 'Select a valid organisation role.']);
        }

        return DB::transaction(function () use ($staff, $organisation, $customer, $role): OrganisationMembership {
            $organisation = Organisation::query()->lockForUpdate()->findOrFail($organisation->id);
            $customer = User::query()->lockForUpdate()->findOrFail($customer->id);

            if (! $customer->hasRole('customer')) {
                throw ValidationException::withMessages(['organisation_id' => 'Only customer accounts can join an organisation.']);
            }

            return $this->createMembership($staff, $organisation, $customer, $role, 'organisation_id', [
                'actor_organisation_role' => null,
                'creation_channel' => 'staff_assisted',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $auditProperties
     */
    private function createMembership(User $actor, Organisation $organisation, User $user, OrganisationRole $role, string $errorKey, array $auditProperties): OrganisationMembership
    {
        if (OrganisationMembership::query()->whereBelongsTo($organisation)->whereBelongsTo($user)->exists()) {
            throw ValidationException::withMessages([$errorKey => self::DUPLICATE_MEMBERSHIP_MESSAGE]);
        }

        $membership = OrganisationMembership::query()->create([
            'organisation_id' => $organisation->id,
            'user_id' => $user->id,
            'role' => $role,
            'joined_at' => now(),
        ]);

        activity('organisation')
            ->performedOn($organisation)
            ->causedBy($actor)
            ->event('organisation.member_added')
            ->withProperties([
                'organisation_id' => $organisation->id,
                'membership_id' => $membership->id,
                'member_user_id' => $user->id,
                'role' => $role->value,
                ...$auditProperties,
            ])
            ->log('Organisation member added');

        return $membership;
    }
}
