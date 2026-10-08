<?php

namespace App\Actions;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateOrganisation
{
    public function handle(User $actor, string $name): Organisation
    {
        Gate::forUser($actor)->authorize('create', Organisation::class);

        return $this->createWithOwner($actor, $actor, $this->validatedName($name), [
            'actor_organisation_role' => OrganisationRole::Owner->value,
        ]);
    }

    /**
     * Creates an organisation on behalf of a customer onboarded by staff during an
     * assisted booking. The customer becomes the initial Owner; the staff member is
     * recorded as the actor for both the organisation and its owner membership.
     */
    public function handleForAssistedCustomer(User $staff, User $owner, string $name): Organisation
    {
        Gate::forUser($staff)->authorize('createForAssistedCustomer', Organisation::class);
        $name = $this->validatedName($name);

        if (! $owner->hasRole('customer')) {
            throw ValidationException::withMessages(['organisation_name' => 'Only customer accounts can own an organisation.']);
        }

        return DB::transaction(function () use ($staff, $owner, $name): Organisation {
            $organisation = $this->createWithOwner($staff, $owner, $name, [
                'owner_user_id' => $owner->id,
                'actor_organisation_role' => null,
                'creation_channel' => 'staff_assisted',
            ]);
            $membership = $organisation->memberships()->sole();

            activity('organisation')
                ->performedOn($organisation)
                ->causedBy($staff)
                ->event('organisation.member_added')
                ->withProperties([
                    'organisation_id' => $organisation->id,
                    'membership_id' => $membership->id,
                    'member_user_id' => $owner->id,
                    'role' => OrganisationRole::Owner->value,
                    'actor_organisation_role' => null,
                    'creation_channel' => 'staff_assisted',
                ])
                ->log('Organisation member added');

            return $organisation;
        });
    }

    private function validatedName(string $name): string
    {
        $name = trim($name);
        Validator::make(['name' => $name], ['name' => ['required', 'string', 'max:255']])->validate();

        return $name;
    }

    /**
     * @param  array<string, mixed>  $auditProperties
     */
    private function createWithOwner(User $causer, User $owner, string $name, array $auditProperties): Organisation
    {
        return DB::transaction(function () use ($causer, $owner, $name, $auditProperties): Organisation {
            User::query()->lockForUpdate()->findOrFail($owner->id);
            $organisation = Organisation::query()->create(['name' => $name]);
            $membership = OrganisationMembership::query()->create([
                'organisation_id' => $organisation->id,
                'user_id' => $owner->id,
                'role' => OrganisationRole::Owner,
                'joined_at' => now(),
            ]);

            activity('organisation')
                ->performedOn($organisation)
                ->causedBy($causer)
                ->event('organisation.created')
                ->withProperties([
                    'organisation_id' => $organisation->id,
                    'membership_id' => $membership->id,
                    ...$auditProperties,
                ])
                ->log('Organisation created');

            return $organisation;
        });
    }
}
