<?php

namespace Tests\Feature\Database;

use App\Models\CustomerCommunication;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MySqlNotificationConcurrencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ConnectionName = 'mysql_notification_concurrency';

    private ?int $customerId = null;

    protected function tearDown(): void
    {
        try {
            if ($this->customerId !== null) {
                $connection = DB::connection(self::ConnectionName);
                $connection->table('notifications')->where('notifiable_type', User::class)->where('notifiable_id', $this->customerId)->delete();
                $connection->table('customer_communications')->where('customer_id', $this->customerId)->delete();
                $connection->table('activity_log')->where('subject_type', User::class)->where('subject_id', $this->customerId)->delete();
                $connection->table('users')->where('id', $this->customerId)->delete();
            }
        } finally {
            DB::disconnect(self::ConnectionName);
            parent::tearDown();
        }
    }

    public function test_simultaneous_delivery_of_one_communication_creates_one_in_app_notification(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        config(['database.connections.'.self::ConnectionName => config('database.connections.mysql')]);
        $original = DB::getDefaultConnection();
        DB::setDefaultConnection(self::ConnectionName);
        try {
            $customer = User::factory()->create();
            $this->customerId = $customer->id;
            $activity = activity('booking')->performedOn($customer)->event('booking.requested')->log('Committed notification race fixture');
            $communication = CustomerCommunication::query()->create([
                'id' => (string) Str::uuid(), 'activity_id' => $activity->id,
                'semantic_key' => 'notification-race:'.$customer->id, 'customer_id' => $customer->id, 'type' => 'booking.requested',
                'payload' => ['type' => 'booking.requested', 'title' => 'Request received', 'body' => 'Awaiting review.',
                    'action_label' => 'View bookings', 'action_url' => '/bookings', 'occurred_at' => now()->toISOString()],
            ]);
        } finally {
            DB::setDefaultConnection($original);
        }

        $outcomes = $this->runWorkers($communication->id);

        $this->assertSame([['delivered' => true], ['delivered' => true]], $outcomes);
        $connection = DB::connection(self::ConnectionName);
        $this->assertSame(1, $connection->table('notifications')->where('communication_id', $communication->id)->count());
        $this->assertNotNull($connection->table('customer_communications')->where('id', $communication->id)->value('database_delivered_at'));
    }

    /** @return list<array{delivered: bool}> */
    private function runWorkers(string $communicationId): array
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        $this->assertNotFalse($server, "Concurrency barrier failed: {$errorMessage} ({$errorCode}).");
        $address = stream_socket_get_name($server, false);
        $this->assertNotFalse($address);
        $workers = [];
        try {
            for ($index = 0; $index < 2; $index++) {
                $process = proc_open([
                    PHP_BINARY, base_path('tests/Support/run-concurrent-notification.php'), "tcp://{$address}", $communicationId,
                ], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path(), [
                    ...getenv(), 'APP_ENV' => 'testing', 'APP_KEY' => (string) config('app.key'), 'DB_CONNECTION' => 'mysql',
                    'DB_HOST' => (string) config('database.connections.mysql.host'), 'DB_PORT' => (string) config('database.connections.mysql.port'),
                    'DB_DATABASE' => (string) config('database.connections.mysql.database'), 'DB_USERNAME' => (string) config('database.connections.mysql.username'),
                    'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
                ]);
                $this->assertIsResource($process);
                fclose($pipes[0]);
                $workers[] = ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
            }
            $sockets = [];
            foreach ($workers as $worker) {
                $socket = stream_socket_accept($server, 15);
                $this->assertNotFalse($socket, 'Notification worker did not reach the barrier.');
                $this->assertSame("ready\n", fgets($socket));
                $sockets[] = $socket;
            }
            foreach ($sockets as $socket) {
                fwrite($socket, "go\n");
                fclose($socket);
            }
            $outcomes = [];
            foreach ($workers as $worker) {
                $stdout = stream_get_contents($worker['stdout']);
                $stderr = stream_get_contents($worker['stderr']);
                fclose($worker['stdout']);
                fclose($worker['stderr']);
                $this->assertSame(0, proc_close($worker['process']), (string) $stderr);
                $outcomes[] = json_decode((string) $stdout, true, flags: JSON_THROW_ON_ERROR);
            }

            return $outcomes;
        } finally {
            fclose($server);
            foreach ($workers as $worker) {
                if (is_resource($worker['process'])) {
                    proc_terminate($worker['process']);
                    proc_close($worker['process']);
                }
            }
        }
    }
}
