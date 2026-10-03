<?php

namespace Tests\Support;

use App\Actions\InitiateBookingPayment;
use App\Actions\ReconcileStripePayment;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Throwable;

class ConcurrentPaymentWorker
{
    /** @param list<string> $arguments */
    public static function run(array $arguments): int
    {
        [, $barrier, $operation, $bookingId, $event] = $arguments;
        try {
            /** @var Application $application */
            $application = require __DIR__.'/../../bootstrap/app.php';
            $application->make(Kernel::class)->bootstrap();
            config(['payments.stripe_live_mode' => false]);
            $application->bind(PaymentService::class, fn (): PaymentService => new class extends PaymentService
            {
                /** @return array{id: string, url: string, payment_intent: string, livemode: bool} */
                public function createCheckout(Payment $payment): array
                {
                    activity('payment')->performedOn($payment)->event('test.checkout_created')->log('Test provider checkout created');

                    return [
                        'id' => 'cs_test_'.$payment->reference,
                        'url' => 'https://checkout.stripe.com/c/test',
                        'payment_intent' => 'pi_'.$payment->reference,
                        'livemode' => false,
                    ];
                }
            });
            $socket = stream_socket_client($barrier, $errorCode, $errorMessage, 10);
            if ($socket === false) {
                throw new \RuntimeException("Payment barrier failed: {$errorMessage} ({$errorCode}).");
            }
            fwrite($socket, "ready\n");
            $instruction = fgets($socket);
            fclose($socket);
            if ($instruction !== "go\n") {
                throw new \RuntimeException('Payment worker was not released.');
            }
            $paymentId = null;
            if ($operation === 'initiate') {
                $booking = Booking::query()->findOrFail((int) $bookingId);
                $payment = $application->make(InitiateBookingPayment::class)->handle($booking->customer, $booking);
                $paymentId = $payment->id;
            } elseif ($operation === 'reconcile') {
                $application->make(ReconcileStripePayment::class)->handle(json_decode($event, true, flags: JSON_THROW_ON_ERROR));
            } else {
                throw new \InvalidArgumentException('Unknown payment worker operation.');
            }
            echo json_encode(['payment_id' => $paymentId], JSON_THROW_ON_ERROR);

            return 0;
        } catch (Throwable $exception) {
            fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n".$exception->getTraceAsString());

            return 1;
        }
    }
}
