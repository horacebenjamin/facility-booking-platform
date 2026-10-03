<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Cashier\Http\Middleware\VerifyWebhookSignature;
use Symfony\Component\HttpFoundation\Response;

class VerifyStripePaymentWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('cashier.webhook.secret');

        abort_unless(is_string($secret) && $secret !== '', 503, 'Payment webhook verification is not configured.');
        abort_unless(is_string($request->header('Stripe-Signature')), 403, 'Payment webhook signature is required.');

        return app(VerifyWebhookSignature::class)->handle($request, $next);
    }
}
