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

class UpdateOrganisationMembership
{
    public function handle(
        User $actor,
        Organisation $organisation,
        OrganisationMembership $membership,
        OrganisationRole $role,
    ): OrganisationMembership {
        Gate::forUser($actor)->authorize('manageMembers', $organisation);

        return DB::transaction(function () use ($actor, $organisation, $membership, $role): OrganisationMembership {
            Organisation::query()->lockForUpdate()->findOrFail($organisation->id);
            $memberships = OrganisationMembership::query()
                ->whereBelongsTo($organisation)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $actorMembership = $memberships->firstWhere('user_id', $actor->id);
            $target = $memberships->firstWhere('id', $membership->id);

            if ($target === null) {
                abort(404);
            }

            if ($actorMembership?->role->canManageMembers() !== true
                || ($actorMembership->role === OrganisationRole::Admin
                    && ($target->role === OrganisationRole::Owner || $role === OrganisationRole::Owner))) {
                throw new AuthorizationException;
            }

            if ($target->role === OrganisationRole::Owner
                && $role !== OrganisationRole::Owner
                && $memberships->where('role', OrganisationRole::Owner)->count() === 1) {
                throw ValidationException::withMessages(['role' => 'An organisation must retain at least one owner.']);
            }

            if ($target->role === $role) {
                return $target;
            }

            $previousRole = $target->role;
            $target->update(['role' => $role]);

            activity('organisation')
                ->performedOn($organisation)
                ->causedBy($actor)
                ->event('organisation.membership_role_changed')
                ->withProperties([
                    'organisation_id' => $organisation->id,
                    'membership_id' => $target->id,
                    'member_user_id' => $target->user_id,
                    'before' => $previousRole->value,
                    'after' => $role->value,
                    'actor_organisation_role' => $actorMembership->role->value,
                ])
                ->log('Organisation membership role changed');

            return $target->fresh() ?? $target;
        });
    }
}
