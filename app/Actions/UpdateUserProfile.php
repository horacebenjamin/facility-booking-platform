<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateUserProfile
{
    /**
     * @param  array<string, string>  $attributes
     */
    public function handle(User $actor, User $profile, array $attributes): void
    {
        DB::transaction(function () use ($actor, $profile, $attributes): void {
            $profile->fill($attributes);

            $changedFields = array_keys($profile->getDirty());

            if ($changedFields === []) {
                return;
            }

            if (in_array('email', $changedFields, true)) {
                $profile->email_verified_at = null;
            }

            $profile->save();

            activity('profile')
                ->performedOn($profile)
                ->causedBy($actor)
                ->event('profile.updated')
                ->withProperties(['changed_fields' => $changedFields])
                ->log('Profile updated');
        });
    }
}
