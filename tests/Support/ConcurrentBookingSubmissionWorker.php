<?php

namespace Tests\Support;

use App\Actions\CreateBookingRequest;
use App\Exceptions\BookingSubmissionUnavailable;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Throwable;

class ConcurrentBookingSubmissionWorker
{
    /**
     * @param  list<string>  $arguments
     */
    public static function run(array $arguments): int
    {
        [, $workerIdentifier, $barrierAddress, $customerId, $resourceId, $startsAt, $endsAt, $equipment] = $arguments;

        try {
            fwrite(STDERR, "Booking concurrency worker [{$workerIdentifier}] booting for barrier [{$barrierAddress}].\n");

            /** @var Application $application */
            $application = require __DIR__.'/../../bootstrap/app.php';
            $application->make(Kernel::class)->bootstrap();

            fwrite(STDERR, "Booking concurrency worker [{$workerIdentifier}] connecting to barrier.\n");
            $socket = stream_socket_client($barrierAddress, $errorCode, $errorMessage, 10);

            if ($socket === false) {
                fwrite(STDERR, "Booking concurrency worker [{$workerIdentifier}] could not connect to the barrier: {$errorMessage} ({$errorCode}).\n");

                return 1;
            }

            fwrite($socket, "ready\n");
            $instruction = fgets($socket);
            fclose($socket);

            if ($instruction !== "go\n") {
                fwrite(STDERR, "Booking concurrency worker [{$workerIdentifier}] was not released by the barrier.\n");

                return 1;
            }

            $equipmentSelections = json_decode($equipment, true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($equipmentSelections)) {
                throw new \UnexpectedValueException('The booking worker received invalid equipment selections.');
            }

            $booking = $application->make(CreateBookingRequest::class)->handle(
                User::query()->findOrFail((int) $customerId),
                (int) $resourceId,
                CarbonImmutable::parse($startsAt, config('app.timezone')),
                CarbonImmutable::parse($endsAt, config('app.timezone')),
                $equipmentSelections,
            );

            echo json_encode(['outcome' => 'succeeded', 'booking_id' => $booking->id], JSON_THROW_ON_ERROR);
        } catch (BookingSubmissionUnavailable) {
            echo json_encode(['outcome' => 'unavailable'], JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            $exceptionClass = $exception::class;
            fwrite(STDERR, "Booking concurrency worker [{$workerIdentifier}] failed: {$exceptionClass}: {$exception->getMessage()}\n");
            echo json_encode([
                'outcome' => 'failed',
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ], JSON_THROW_ON_ERROR);

            return 1;
        }

        return 0;
    }
}
