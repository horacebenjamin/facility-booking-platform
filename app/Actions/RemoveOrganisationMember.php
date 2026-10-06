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

class RemoveOrganisationMember
{
    public function handle(User $actor, Organisation $organisation, OrganisationMembership $membership): void
    {
        Gate::forUser($actor)->authorize('manageMembers', $organisation);

        DB::transaction(function () use ($actor, $organisation, $membership): void {
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
                || ($actorMembership->role === OrganisationRole::Admin && $target->role === OrganisationRole::Owner)) {
                throw new AuthorizationException;
            }

            if ($target->role === OrganisationRole::Owner
                && $memberships->where('role', OrganisationRole::Owner)->count() === 1) {
                throw ValidationException::withMessages(['membership' => 'An organisation must retain at least one owner.']);
            }

            $properties = [
                'organisation_id' => $organisation->id,
                'membership_id' => $target->id,
                'member_user_id' => $target->user_id,
                'role' => $target->role->value,
                'actor_organisation_role' => $actorMembership->role->value,
            ];
            $target->delete();

            activity('organisation')
                ->performedOn($organisation)
                ->causedBy($actor)
                ->event('organisation.member_removed')
                ->withProperties($properties)
                ->log('Organisation member removed');
        });
    }
}
