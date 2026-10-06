<?php

namespace App\Models;

use App\Enums\OrganisationRole;
use Carbon\CarbonInterface;
use Database\Factories\OrganisationMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int $user_id
 * @property OrganisationRole $role
 * @property CarbonInterface $joined_at
 */
#[Fillable(['organisation_id', 'user_id', 'role', 'joined_at'])]
class OrganisationMembership extends Model
{
    /** @use HasFactory<OrganisationMembershipFactory> */
    use HasFactory;

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => OrganisationRole::class,
            'joined_at' => 'datetime',
        ];
    }
}
