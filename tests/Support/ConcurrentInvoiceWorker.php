<?php

namespace Tests\Support;

use App\Actions\IssueInvoice;
use App\Actions\RecordManualInvoicePayment;
use App\Exceptions\InvoiceUnavailable;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Throwable;

class ConcurrentInvoiceWorker
{
    /** @param list<string> $arguments */
    public static function run(array $arguments): int
    {
        [, $barrier, $operation, $bookingId, $context] = $arguments;
        try {
            /** @var Application $application */
            $application = require __DIR__.'/../../bootstrap/app.php';
            $application->make(Kernel::class)->bootstrap();
            $data = json_decode($context, true, flags: JSON_THROW_ON_ERROR);
            $socket = stream_socket_client($barrier, $errorCode, $errorMessage, 10);
            if ($socket === false) {
                throw new \RuntimeException("Invoice barrier failed: {$errorMessage} ({$errorCode}).");
            }
            fwrite($socket, "ready\n");
            $instruction = fgets($socket);
            fclose($socket);
            if ($instruction !== "go\n") {
                throw new \RuntimeException('Invoice worker was not released.');
            }
            $accepted = true;
            try {
                $actor = User::findOrFail($data['manager_id']);
                if ($operation === 'issue') {
                    $application->make(IssueInvoice::class)->handle($actor, $data['booking_ids'] ?? [(int) $bookingId]);
                } elseif ($operation === 'settle') {
                    $application->make(RecordManualInvoicePayment::class)->handle($actor, Invoice::findOrFail($data['invoice_id']), 'BANK-CONCURRENT', 'Verified externally.');
                } else {
                    throw new \InvalidArgumentException('Unknown invoice worker operation.');
                }
            } catch (InvoiceUnavailable) {
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
