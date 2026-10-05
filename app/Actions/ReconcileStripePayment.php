<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Events\LifecycleNotificationRequested;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\CentreReservationLock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class ReconcileStripePayment
{
    private const SupportedEvents = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
        'checkout.session.async_payment_failed',
        'checkout.session.expired',
    ];

    public function __construct(
        private ConfirmPaidBooking $confirmPaidBooking,
        private CentreReservationLock $centreReservationLock,
    ) {}

    /** @param array<string, mixed> $event */
    public function handle(array $event): void
    {
        Validator::make($event, [
            'id' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
        ])->validate();

        if (! in_array($event['type'], self::SupportedEvents, true)) {
            return;
        }

        Validator::make($event, [
            'created' => ['required', 'integer', 'min:1'],
            'livemode' => ['required', 'boolean'],
            'data.object' => ['required', 'array'],
            'data.object.id' => ['required', 'string', 'max:255'],
        ])->validate();
        /** @var array<string, mixed> $session */
        $session = $event['data']['object'];
        $reference = $session['metadata']['payment_reference'] ?? null;
        $candidate = Payment::query()->where('provider', 'stripe')
            ->where(function ($query) use ($session, $reference): void {
                $query->where('provider_session_id', $session['id']);

                if (is_string($reference)) {
                    $query->orWhere('reference', $reference);
                }
            })->first();

        if ($candidate === null) {
            return;
        }

        $centreId = $this->centreReservationLock->centreIdForBooking($candidate->booking_id);

        DB::transaction(function () use ($candidate, $event, $session, $centreId): void {
            $this->centreReservationLock->lock($centreId);
            $booking = Booking::query()->lockForUpdate()->findOrFail($candidate->booking_id);

            if ($booking->centre_id !== $centreId) {
                throw new ServiceUnavailableHttpException(30, 'The booking centre changed during reconciliation.');
            }

            $payment = Payment::query()->lockForUpdate()->findOrFail($candidate->id);

            if ($payment->provider_session_id === null) {
                throw new ServiceUnavailableHttpException(30, 'Payment provider reference is awaiting reconciliation.');
            }

            $createdAt = CarbonImmutable::createFromTimestampUTC((int) $event['created']);
            $intent = $session['payment_intent'] ?? null;

            if ($payment->provider !== 'stripe'
                || ($event['account'] ?? null) !== null
                || ($session['object'] ?? null) !== 'checkout.session'
                || ($session['mode'] ?? null) !== 'payment'
                || $session['id'] !== $payment->provider_session_id
                || ($session['metadata']['payment_reference'] ?? null) !== $payment->reference
                || ($session['client_reference_id'] ?? null) !== $payment->reference
                || ($session['amount_total'] ?? null) !== $payment->amount_minor
                || ($session['currency'] ?? null) !== strtolower($payment->currency)
                || ($event['livemode'] ?? null) !== $payment->live_mode
                || ($session['livemode'] ?? null) !== $payment->live_mode
                || $payment->live_mode !== (bool) config('payments.stripe_live_mode')
                || $payment->customer_id !== $booking->customer_id
                || $createdAt->lt($payment->created_at)
                || $createdAt->gt(now()->addMinutes(5))
                || ($intent !== null && (! is_string($intent) || ! str_starts_with($intent, 'pi_')))
                || ($payment->provider_payment_intent_id !== null && $payment->provider_payment_intent_id !== $intent)) {
                throw ValidationException::withMessages(['event' => 'Payment provider context does not match.']);
            }

            $status = $this->outcome($event['type'], $session);

            if ($status === PaymentStatus::Succeeded && $intent === null) {
                throw ValidationException::withMessages(['event' => 'Successful payment has no provider payment reference.']);
            }

            $priorEvent = DB::table('payment_provider_events')->where('provider', 'stripe')
                ->where('provider_event_id', $event['id'])->first();

            if ($priorEvent !== null) {
                if ($priorEvent->payment_id !== $payment->id) {
                    throw ValidationException::withMessages(['event' => 'Payment event identity does not match.']);
                }

                return;
            }

            DB::table('payment_provider_events')->insert([
                'payment_id' => $payment->id,
                'provider' => 'stripe',
                'provider_event_id' => $event['id'],
                'event_type' => $event['type'],
                'provider_created_at' => $createdAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($payment->status === PaymentStatus::Succeeded || $payment->status === $status) {
                return;
            }

            if ($status === PaymentStatus::Processing && $payment->status !== PaymentStatus::Pending) {
                return;
            }

            $previousStatus = $payment->status;
            $payment->update([
                'status' => $status,
                'provider_payment_intent_id' => $intent ?? $payment->provider_payment_intent_id,
                'succeeded_at' => $status === PaymentStatus::Succeeded ? $createdAt : null,
            ]);
            $outcomeActivity = activity('payment')->performedOn($payment)->event('payment.'.$status->value)
                ->withProperties(['provider_event_id' => $event['id'], 'booking_id' => $booking->id])
                ->log('Verified payment outcome reconciled');

            if ($status === PaymentStatus::Succeeded
                && (in_array($previousStatus, [PaymentStatus::Failed, PaymentStatus::Expired], true)
                    || ! $this->confirmPaidBooking->handle($booking, $payment, $createdAt))) {
                $payment->update(['reconciliation_issue' => 'Payment received; booking requires management review.']);
                activity('payment')->performedOn($payment)->event('payment.review_required')
                    ->withProperties(['booking_id' => $booking->id])
                    ->log('Received payment requires booking review');
            }

            if ($status === PaymentStatus::Succeeded && $outcomeActivity instanceof Activity) {
                LifecycleNotificationRequested::dispatch($outcomeActivity->id);
            }
        });
    }

    /** @param array<string, mixed> $session */
    private function outcome(string $type, array $session): PaymentStatus
    {
        $status = $session['status'] ?? null;
        $paymentStatus = $session['payment_status'] ?? null;

        if ($type === 'checkout.session.expired' && $status === 'expired' && $paymentStatus === 'unpaid') {
            return PaymentStatus::Expired;
        }

        if ($status === 'complete') {
            if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)
                && $paymentStatus === 'paid') {
                return PaymentStatus::Succeeded;
            }

            if ($type === 'checkout.session.completed' && $paymentStatus === 'unpaid') {
                return PaymentStatus::Processing;
            }

            if ($type === 'checkout.session.async_payment_failed' && $paymentStatus === 'unpaid') {
                return PaymentStatus::Failed;
            }
        }

        throw ValidationException::withMessages(['event' => 'Unsupported payment provider outcome.']);
    }
}
