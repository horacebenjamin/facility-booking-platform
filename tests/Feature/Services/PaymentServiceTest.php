<?php

namespace Tests\Feature\Services;

use App\Exceptions\PaymentUnavailable;
use App\Models\Payment;
use App\Services\PaymentService;
use Mockery;
use PHPUnit\Framework\Attributes\TestWith;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(CurlClient::instance());
        parent::tearDown();
    }

    public function test_cashier_checkout_sends_persisted_money_and_durable_idempotency_key_without_network(): void
    {
        config(['cashier.secret' => 'sk_test_fake']);
        $payment = Payment::factory()->make([
            'booking_id' => 1, 'customer_id' => 1, 'reference' => 'opaque-attempt',
            'checkout_parameters' => [
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => ['currency' => 'gbp', 'unit_amount' => 5000, 'product_data' => ['name' => 'Booking']],
                    'quantity' => 1,
                ]],
            ],
        ]);
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('request')->twice()
            ->withArgs(function ($method, $url, $headers, $parameters): bool {
                $this->assertSame('post', $method);
                $this->assertSame('https://api.stripe.com/v1/checkout/sessions', $url);
                $this->assertContains('Idempotency-Key: booking-payment-opaque-attempt', $headers);
                $this->assertSame(5000, $parameters['line_items'][0]['price_data']['unit_amount']);
                $this->assertSame('gbp', $parameters['line_items'][0]['price_data']['currency']);
                $this->assertSame(['card'], $parameters['payment_method_types']);

                return true;
            })
            ->andReturn([
                json_encode(['id' => 'cs_test', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/test', 'payment_intent' => null, 'livemode' => false], JSON_THROW_ON_ERROR),
                200, [],
            ]);
        ApiRequestor::setHttpClient($client);

        $first = app(PaymentService::class)->createCheckout($payment);
        $retry = app(PaymentService::class)->createCheckout($payment);

        $this->assertSame($first, $retry);
        $this->assertSame('cs_test', $first['id']);
        $this->assertNull($first['payment_intent']);
    }

    #[TestWith([''])]
    #[TestWith(['sk_live_fake'])]
    public function test_missing_or_wrong_environment_key_fails_before_any_provider_call(string $secret): void
    {
        config(['cashier.secret' => $secret]);
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldNotReceive('request');
        ApiRequestor::setHttpClient($client);
        $payment = Payment::factory()->make(['booking_id' => 1, 'customer_id' => 1]);

        $this->expectException(PaymentUnavailable::class);
        app(PaymentService::class)->createCheckout($payment);
    }
}
