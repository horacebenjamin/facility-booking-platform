<?php

namespace App\Actions;

use App\Models\Booking;
use App\Models\CustomerInvoiceTerms;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class SetCustomerInvoiceTerms
{
    public function handle(User $actor, Booking $booking, bool $enabled, int $termDays): CustomerInvoiceTerms
    {
        Gate::forUser($actor)->authorize('manageInvoiceTerms', $booking);
        Validator::make(['term_days' => $termDays], ['term_days' => ['required', 'integer', 'between:1,365']])->validate();

        return DB::transaction(function () use ($actor, $booking, $enabled, $termDays): CustomerInvoiceTerms {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            Gate::forUser($actor)->authorize('manageInvoiceTerms', $booking);
            User::query()->lockForUpdate()->findOrFail($booking->customer_id);
            $terms = CustomerInvoiceTerms::query()->where('customer_id', $booking->customer_id)
                ->where('centre_id', $booking->centre_id)->lockForUpdate()->first();
            $before = $terms === null ? null : ['enabled' => $terms->enabled, 'term_days' => $terms->term_days];

            if ($terms !== null && $terms->enabled === $enabled && $terms->term_days === $termDays) {
                return $terms;
            }

            $attributes = ['enabled' => $enabled, 'term_days' => $termDays, 'authorised_by' => $actor->id, 'authorised_at' => now()];
            if ($terms === null) {
                $terms = CustomerInvoiceTerms::query()->create(['customer_id' => $booking->customer_id, 'centre_id' => $booking->centre_id, ...$attributes]);
            } else {
                $terms->update($attributes);
            }
            activity('invoice')->performedOn($terms)->causedBy($actor)->event('invoice.terms_changed')
                ->withProperties(['customer_id' => $booking->customer_id, 'centre_id' => $booking->centre_id, 'before' => $before, 'after' => ['enabled' => $enabled, 'term_days' => $termDays]])
                ->log('Customer invoice terms authorised');

            return $terms;
        });
    }
}
