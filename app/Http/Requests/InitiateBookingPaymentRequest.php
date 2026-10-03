<?php

namespace App\Http\Requests;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;

class InitiateBookingPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        abort_unless($booking instanceof Booking && $this->user()?->id === $booking->customer_id, 404);

        return $this->user()->can('pay', $booking);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return array_fill_keys([
            'amount', 'amount_minor', 'currency', 'status', 'payment_status', 'financial_status', 'booking_status',
            'customer_id', 'booking_id', 'provider', 'provider_session_id', 'provider_payment_intent_id',
            'payment_intent', 'session_id', 'checkout_url',
        ], ['prohibited']);
    }
}
