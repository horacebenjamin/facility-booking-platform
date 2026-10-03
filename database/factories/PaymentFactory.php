<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'reference' => fake()->uuid(),
            'booking_id' => Booking::factory(),
            'customer_id' => fn (array $attributes): int => Booking::query()->whereKey($attributes['booking_id'])->sole()->customer_id,
            'provider' => 'stripe',
            'provider_session_id' => 'cs_test_'.fake()->uuid(),
            'provider_payment_intent_id' => null,
            'checkout_url' => 'https://checkout.stripe.com/c/pay/test',
            'checkout_parameters' => [],
            'amount_minor' => 5000,
            'currency' => 'GBP',
            'status' => PaymentStatus::Pending,
            'live_mode' => false,
            'session_expires_at' => now()->addHours(1),
        ];
    }
}
