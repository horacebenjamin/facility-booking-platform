<?php

namespace App\Models;

use Database\Factories\OrganisationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property string $name
 */
#[Fillable(['name'])]
class Organisation extends Model
{
    /** @use HasFactory<OrganisationFactory> */
    use HasFactory;

    /** @return HasMany<OrganisationMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganisationMembership::class);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<CustomerInvoiceTerms, $this> */
    public function invoiceTerms(): HasMany
    {
        return $this->hasMany(CustomerInvoiceTerms::class);
    }

    /** @return MorphMany<Activity, $this> */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function membershipFor(User $user): ?OrganisationMembership
    {
        return $this->memberships()->whereBelongsTo($user)->first();
    }
}
