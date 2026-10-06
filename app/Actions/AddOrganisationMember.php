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

            if (OrganisationMembership::query()->whereBelongsTo($organisation)->whereBelongsTo($user)->exists()) {
                throw ValidationException::withMessages(['email' => 'This customer is already a member of the organisation.']);
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
                    'actor_organisation_role' => $actorMembership->role->value,
                ])
                ->log('Organisation member added');

            return $membership;
        });
    }
}
