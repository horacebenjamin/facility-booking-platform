<?php

namespace Tests\Support;

use App\Models\CustomerCommunication;
use App\Models\User;
use App\Notifications\BookingRequestReceivedNotification;
use App\Notifications\Channels\CustomerDatabaseChannel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Throwable;

class ConcurrentNotificationWorker
{
    /** @param list<string> $arguments */
    public static function run(array $arguments): int
    {
        [, $barrier, $communicationId] = $arguments;
        try {
            /** @var Application $application */
            $application = require __DIR__.'/../../bootstrap/app.php';
            $application->make(Kernel::class)->bootstrap();
            $socket = stream_socket_client($barrier, $errorCode, $errorMessage, 10);
            if ($socket === false) {
                throw new \RuntimeException("Notification barrier failed: {$errorMessage} ({$errorCode}).");
            }
            fwrite($socket, "ready\n");
            $instruction = fgets($socket);
            fclose($socket);
            if ($instruction !== "go\n") {
                throw new \RuntimeException('Notification worker was not released.');
            }
            $communication = CustomerCommunication::query()->findOrFail($communicationId);
            $customer = User::query()->findOrFail($communication->customer_id);
            $application->make(CustomerDatabaseChannel::class)->send($customer, new BookingRequestReceivedNotification($communicationId));
            echo json_encode(['delivered' => $communication->fresh()->database_delivered_at !== null], JSON_THROW_ON_ERROR);

            return 0;
        } catch (Throwable $exception) {
            fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n".$exception->getTraceAsString());

            return 1;
        }
    }
}
