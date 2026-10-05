<?php

namespace Tests\Support;

use App\Actions\CompleteBooking;
use App\Actions\RecordArrival;
use App\Actions\RecordNoShow;
use App\Exceptions\AttendanceTransitionUnavailable;
use App\Models\Booking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithTime;
use Throwable;

class ConcurrentAttendanceWorker
{
    /** @param list<string> $arguments */
    public static function run(array $arguments): int
    {
        [, $barrier, $operation, $bookingId, $actorId, $time] = $arguments;
        try {
            $application = require __DIR__.'/../../bootstrap/app.php';
            $application->make(Kernel::class)->bootstrap();
            $clock = new class
            {
                use InteractsWithTime;
            };
            $clock->travelTo(CarbonImmutable::parse($time, config('app.timezone')));
            $socket = stream_socket_client($barrier, $errorCode, $errorMessage, 10);
            if ($socket === false) {
                throw new \RuntimeException("Attendance barrier failed: {$errorMessage} ({$errorCode})");
            }
            fwrite($socket, "ready\n");
            $instruction = fgets($socket);
            fclose($socket);
            if ($instruction !== "go\n") {
                throw new \RuntimeException('Attendance worker not released.');
            }
            $accepted = true;
            try {
                $application->make(match ($operation) {
                    'arrival' => RecordArrival::class,
                    'no-show' => RecordNoShow::class,
                    'complete' => CompleteBooking::class,
                })->handle(User::findOrFail($actorId), Booking::findOrFail($bookingId));
            } catch (AttendanceTransitionUnavailable) {
                $accepted = false;
            }
            echo json_encode(['accepted' => $accepted], JSON_THROW_ON_ERROR);

            return 0;
        } catch (Throwable $exception) {
            fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n".$exception->getTraceAsString());

            return 1;
        }
    }
}
