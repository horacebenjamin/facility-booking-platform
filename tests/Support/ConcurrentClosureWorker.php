<?php

namespace Tests\Support;

use App\Actions\ApproveBooking;
use App\Actions\CreateAvailabilityBlock;
use App\Actions\CreateBookingRequest;
use App\Actions\ReconcileStripePayment;
use App\Actions\ResolveClosureImpact;
use App\Events\LifecycleNotificationRequested;
use App\Exceptions\BookingLifecycleTransitionUnavailable;
use App\Exceptions\BookingSubmissionUnavailable;
use App\Models\AvailabilityBlock;
use App\Models\AvailabilityBlockBookingImpact;
use App\Models\Booking;
use App\Models\Centre;
use App\Models\Payment;
use App\Models\User;
use App\Services\CentreReservationLock;
use App\Services\ClosureImpactService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Throwable;

class ConcurrentClosureWorker
{
    /** @param list<string> $arguments */
    public static function run(array $arguments): int
    {
        [, $barrier, $operation, $actorId, $resourceId, $centreId, $blockId, $hold] = $arguments;
        try {
            $application = require __DIR__.'/../../bootstrap/app.php';
            $application->make(Kernel::class)->bootstrap();
            Event::fake([LifecycleNotificationRequested::class]);
            $clock = new class
            {
                use InteractsWithTime;
            };
            $clock->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00', config('app.timezone')));
            $socket = stream_socket_client($barrier, $errorCode, $errorMessage, 10);
            if ($socket === false) {
                throw new \RuntimeException("Closure barrier failed: {$errorMessage} ({$errorCode})");
            }
            stream_set_timeout($socket, 20);
            $connectionId = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
            fwrite($socket, json_encode(['connection_id' => $connectionId], JSON_THROW_ON_ERROR)."\n");
            if (fgets($socket) !== "go\n") {
                throw new \RuntimeException('Closure worker was not released.');
            }
            $lock = new class($socket, $hold === 'hold') extends CentreReservationLock
            {
                /** @param resource $socket */
                public function __construct(private $socket, private bool $hold) {}

                public function lock(int $centreId): Centre
                {
                    fwrite($this->socket, "locking\n");
                    $centre = parent::lock($centreId);
                    fwrite($this->socket, "locked\n");
                    if ($this->hold && fgets($this->socket) !== "commit\n") {
                        throw new \RuntimeException('Closure lock holder was not released.');
                    }

                    return $centre;
                }
            };
            $application->instance(CentreReservationLock::class, $lock);
            $actor = User::query()->findOrFail((int) $actorId);
            try {
                $result = match ($operation) {
                    'approve' => ['booking_id' => $application->make(ApproveBooking::class)->handle($actor, Booking::query()->findOrFail((int) $blockId))->id],
                    'confirm' => self::confirm($application->make(ReconcileStripePayment::class), (int) $blockId),
                    'booking' => ['booking_id' => $application->make(CreateBookingRequest::class)->handle(
                        $actor, (int) $resourceId,
                        CarbonImmutable::parse('2026-10-05 18:00:00', config('app.timezone')),
                        CarbonImmutable::parse('2026-10-05 19:00:00', config('app.timezone')),
                    )->id],
                    'closure' => ['block_id' => $application->make(CreateAvailabilityBlock::class)->handle($actor, [
                        'centre_id' => (int) $centreId, 'scope' => 'centre', 'type' => 'maintenance',
                        'starts_at' => '2026-10-05 17:00:00', 'ends_at' => '2026-10-05 20:00:00',
                        'reason' => 'Concurrency maintenance fixture',
                    ])->id],
                    'resolve' => ['impact_id' => $application->make(ResolveClosureImpact::class)->handle(
                        $actor, AvailabilityBlockBookingImpact::query()->findOrFail((int) $blockId),
                        $hold === 'hold' ? 'First manager resolution' : 'Conflicting second resolution',
                    )->id],
                    'detect' => DB::transaction(function () use ($application, $lock, $actor, $centreId, $blockId): array {
                        $lock->lock((int) $centreId);
                        $count = $application->make(ClosureImpactService::class)->detect(
                            $actor, AvailabilityBlock::query()->findOrFail((int) $blockId),
                            CarbonImmutable::now(config('app.timezone')),
                        );

                        return ['detected_count' => $count];
                    }),
                    default => throw new \RuntimeException('Unknown closure worker operation.'),
                };
                echo json_encode(['outcome' => 'accepted', ...$result], JSON_THROW_ON_ERROR);
            } catch (BookingSubmissionUnavailable) {
                echo json_encode(['outcome' => 'unavailable'], JSON_THROW_ON_ERROR);
            } catch (BookingLifecycleTransitionUnavailable) {
                echo json_encode(['outcome' => 'unavailable'], JSON_THROW_ON_ERROR);
            } catch (ValidationException $exception) {
                if ($operation !== 'resolve') {
                    throw $exception;
                }
                echo json_encode(['outcome' => 'rejected'], JSON_THROW_ON_ERROR);
            }
            fclose($socket);

            return 0;
        } catch (Throwable $exception) {
            fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n".$exception->getTraceAsString());

            return 1;
        }
    }

    /** @return array{booking_id: int} */
    private static function confirm(ReconcileStripePayment $reconcile, int $bookingId): array
    {
        $payment = Payment::query()->where('booking_id', $bookingId)->sole();
        $reconcile->handle([
            'id' => 'evt_closure_race_'.$payment->id, 'type' => 'checkout.session.completed',
            'created' => now()->getTimestamp(), 'livemode' => false,
            'data' => ['object' => [
                'id' => $payment->provider_session_id, 'object' => 'checkout.session', 'mode' => 'payment', 'livemode' => false,
                'metadata' => ['payment_reference' => $payment->reference], 'client_reference_id' => $payment->reference,
                'amount_total' => $payment->amount_minor, 'currency' => strtolower($payment->currency),
                'payment_intent' => 'pi_closure_race_'.$payment->id, 'status' => 'complete', 'payment_status' => 'paid',
            ]],
        ]);

        return ['booking_id' => $bookingId];
    }
}
