<?php

namespace App\Services;

use App\Exceptions\PaymentUnavailable;
use App\Models\Payment;
use Laravel\Cashier\Cashier;
use Stripe\HttpClient\CurlClient;

class PaymentService
{
    /** @return array{id: string, url: string, payment_intent: ?string, livemode: bool} */
    public function createCheckout(Payment $payment): array
    {
        $secret = config('cashier.secret');

        if (! is_string($secret) || $secret === ''
            || ! str_starts_with($secret, $payment->live_mode ? 'sk_live_' : 'sk_test_')) {
            throw new PaymentUnavailable('Secure card payment is not configured. Please contact the centre.');
        }

        $client = CurlClient::instance();
        $client->setConnectTimeout(5);
        $client->setTimeout(15);
        $session = Cashier::stripe()->checkout->sessions->create(
            $payment->checkout_parameters,
            ['idempotency_key' => 'booking-payment-'.$payment->reference],
        );

        return [
            'id' => (string) $session->id,
            'url' => (string) $session->url,
            'payment_intent' => is_string($session->payment_intent) ? $session->payment_intent : null,
            'livemode' => (bool) $session->livemode,
        ];
    }
}
