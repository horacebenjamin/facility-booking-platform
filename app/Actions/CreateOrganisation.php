<?php

namespace App\Actions;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CreateOrganisation
{
    public function handle(User $actor, string $name): Organisation
    {
        Gate::forUser($actor)->authorize('create', Organisation::class);
        $name = trim($name);
        Validator::make(['name' => $name], ['name' => ['required', 'string', 'max:255']])->validate();

        return DB::transaction(function () use ($actor, $name): Organisation {
            User::query()->lockForUpdate()->findOrFail($actor->id);
            $organisation = Organisation::query()->create(['name' => $name]);
            $membership = OrganisationMembership::query()->create([
                'organisation_id' => $organisation->id,
                'user_id' => $actor->id,
                'role' => OrganisationRole::Owner,
                'joined_at' => now(),
            ]);

            activity('organisation')
                ->performedOn($organisation)
                ->causedBy($actor)
                ->event('organisation.created')
                ->withProperties([
                    'organisation_id' => $organisation->id,
                    'membership_id' => $membership->id,
                    'actor_organisation_role' => OrganisationRole::Owner->value,
                ])
                ->log('Organisation created');

            return $organisation;
        });
    }
}
