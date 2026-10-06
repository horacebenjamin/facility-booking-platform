<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Exceptions\PaymentUnavailable;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Services\BookingPaymentEligibility;
use App\Services\PaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class InitiateBookingPayment
{
    public function __construct(
        private BookingPaymentEligibility $eligibility,
        private PaymentService $paymentService,
    ) {}

    public function handle(User $actor, Booking $booking): Payment
    {
        Gate::forUser($actor)->authorize('pay', $booking);

        $payment = DB::transaction(function () use ($actor, $booking): Payment {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            Gate::forUser($actor)->authorize('pay', $booking);
            $now = CarbonImmutable::now();
            $this->eligibility->assertLifecycle($booking, $now);

            if ($booking->payment_due_at === null || $booking->payment_due_at->lte($now)) {
                throw new PaymentUnavailable('The payment deadline has passed. Please contact the centre.');
            }

            $this->eligibility->assertProtection($booking);
            $snapshot = $this->eligibility->snapshot($booking);
            $payments = $booking->payments()->orderBy('id')->lockForUpdate()->get();

            if ($payments->contains(fn (Payment $payment): bool => $payment->status === PaymentStatus::Succeeded)) {
                throw new PaymentUnavailable('A payment has already been received for this booking.');
            }

            $active = $payments->first(fn (Payment $payment): bool => in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Processing], true));

            if ($active !== null) {
                if ($active->amount_minor !== $snapshot->final_total_minor || $active->currency !== $snapshot->currency
                    || $active->customer_id !== $booking->customer_id || $active->provider !== 'stripe'
                    || $active->live_mode !== (bool) config('payments.stripe_live_mode')
                    || $active->session_expires_at->lte($now)) {
                    throw new PaymentUnavailable('A previous payment requires reconciliation. Please contact the centre.');
                }

                return $active;
            }

            $expiresAt = CarbonImmutable::instance($booking->payment_due_at)->min($now->addHours(23))->floorSecond();

            if ($expiresAt->lt($now->addMinutes(30))) {
                throw new PaymentUnavailable('A new secure checkout needs at least 30 minutes before the payment deadline. Please contact the centre.');
            }

            $reference = (string) Str::uuid();
            $returnUrl = route('bookings.payment.show', $booking);
            $payment = $booking->payments()->create([
                'reference' => $reference,
                'customer_id' => $booking->customer_id,
                'provider' => 'stripe',
                'amount_minor' => $snapshot->final_total_minor,
                'currency' => $snapshot->currency,
                'status' => PaymentStatus::Pending,
                'live_mode' => (bool) config('payments.stripe_live_mode'),
                'session_expires_at' => $expiresAt,
                'checkout_parameters' => [
                    'mode' => 'payment',
                    'payment_method_types' => ['card'],
                    'client_reference_id' => $reference,
                    'metadata' => ['payment_reference' => $reference],
                    'payment_intent_data' => ['metadata' => ['payment_reference' => $reference]],
                    'expires_at' => $expiresAt->getTimestamp(),
                    'success_url' => $returnUrl,
                    'cancel_url' => $returnUrl,
                    'line_items' => [[
                        'price_data' => [
                            'currency' => strtolower($snapshot->currency),
                            'unit_amount' => $snapshot->final_total_minor,
                            'product_data' => ['name' => 'Facility booking '.$booking->reference],
                        ],
                        'quantity' => 1,
                    ]],
                ],
            ]);
            activity('payment')->performedOn($payment)->causedBy($actor)
                ->event('payment.initiated')->withProperties([
                    'booking_id' => $booking->id,
                    'organisation_id' => $booking->organisation_id,
                    'actor_organisation_role' => $booking->organisation?->membershipFor($actor)?->role->value,
                ])
                ->log('Booking payment initiated');

            return $payment;
        });

        try {
            return DB::transaction(function () use ($actor, $booking, $payment): Payment {
                $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
                Gate::forUser($actor)->authorize('pay', $booking);
                $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                $this->eligibility->assertLifecycle($booking, CarbonImmutable::now());
                $this->eligibility->assertProtection($booking);
                $snapshot = $this->eligibility->snapshot($booking);

                if ($payment->amount_minor !== $snapshot->final_total_minor || $payment->currency !== $snapshot->currency
                    || $payment->customer_id !== $booking->customer_id || $payment->provider !== 'stripe'
                    || $payment->live_mode !== (bool) config('payments.stripe_live_mode')) {
                    throw new PaymentUnavailable('The reserved payment requires review. Please contact the centre.');
                }

                if ($booking->payment_due_at === null || $booking->payment_due_at->lte(now())
                    || $payment->session_expires_at->lte(now())
                    || ! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Processing], true)) {
                    throw new PaymentUnavailable('This payment opportunity is no longer available.');
                }

                if ($payment->provider_session_id !== null && $payment->checkout_url !== null) {
                    return $payment;
                }

                // The durable attempt survives a timeout or rollback; retries send identical parameters and key.
                $session = $this->paymentService->createCheckout($payment);

                if (! str_starts_with($session['id'], 'cs_')
                    || $session['livemode'] !== $payment->live_mode
                    || parse_url($session['url'], PHP_URL_SCHEME) !== 'https'
                    || parse_url($session['url'], PHP_URL_HOST) !== 'checkout.stripe.com') {
                    throw new PaymentUnavailable('The payment provider response requires review.');
                }

                $payment->update([
                    'provider_session_id' => $session['id'],
                    'provider_payment_intent_id' => $session['payment_intent'],
                    'checkout_url' => $session['url'],
                ]);

                return $payment;
            });
        } catch (PaymentUnavailable $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Stripe checkout outcome is uncertain.', [
                'payment_reference' => $payment->reference,
                'exception_type' => $exception::class,
            ]);

            throw new PaymentUnavailable('Payment could not be started yet. Retry safely to resume the same payment.', previous: $exception);
        }
    }
}
