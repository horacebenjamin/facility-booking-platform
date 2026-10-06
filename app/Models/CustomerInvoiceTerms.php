<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $customer_id
 * @property int|null $organisation_id
 * @property int $centre_id
 * @property bool $enabled
 * @property int $term_days
 * @property int $authorised_by
 * @property CarbonInterface $authorised_at
 */
#[Fillable(['customer_id', 'organisation_id', 'centre_id', 'enabled', 'term_days', 'authorised_by', 'authorised_at'])]
class CustomerInvoiceTerms extends Model
{
    /**
     * Terms held by the party financially responsible for the booking at its centre:
     * the organisation for organisation bookings, otherwise the individual customer.
     *
     * @param  Builder<CustomerInvoiceTerms>  $query
     */
    #[Scope]
    protected function responsibleFor(Builder $query, Booking $booking): void
    {
        $query->where('centre_id', $booking->centre_id)->when(
            $booking->organisation_id === null,
            fn (Builder $query) => $query->where('customer_id', $booking->customer_id)->whereNull('organisation_id'),
            fn (Builder $query) => $query->where('organisation_id', $booking->organisation_id)->whereNull('customer_id'),
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'term_days' => 'integer', 'authorised_at' => 'datetime'];
    }
}
