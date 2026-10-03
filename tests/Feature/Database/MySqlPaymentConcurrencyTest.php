<?php

namespace Tests\Feature\Database;

use App\Models\AllocationOccupancy;
use App\Models\AllocationUnit;
use App\Models\Booking;
use App\Models\BookingPriceLine;
use App\Models\BookingPriceSnapshot;
use App\Models\Payment;
use App\Models\User;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MySqlPaymentConcurrencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ConnectionName = 'mysql_payment_concurrency';

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', DB::connection()->getDriverName());
        config(['database.connections.'.self::ConnectionName => config('database.connections.mysql')]);
        config(['payments.stripe_live_mode' => false]);
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->ids['booking'])) {
                $db = $this->connection();
                $paymentIds = $db->table('payments')->where('booking_id', $this->ids['booking'])->pluck('id');
                $db->table('payment_provider_events')->whereIn('payment_id', $paymentIds)->delete();
                $db->table('activity_log')->where(function ($query): void {
                    $query->where('subject_type', Booking::class)->where('subject_id', $this->ids['booking']);
                })->orWhere(function ($query) use ($paymentIds): void {
                    $query->where('subject_type', Payment::class)->whereIn('subject_id', $paymentIds);
                })->delete();
                $db->table('payments')->where('booking_id', $this->ids['booking'])->delete();
                $db->table('allocation_occupancy_allocation_unit')->where('allocation_occupancy_id', $this->ids['occupancy'])->delete();
                foreach (['allocation_occupancies' => 'occupancy', 'booking_price_lines' => 'line', 'booking_price_snapshots' => 'snapshot', 'bookings' => 'booking'] as $table => $key) {
                    $db->table($table)->where('id', $this->ids[$key])->delete();
                }
                $db->table('allocation_unit_resource')->where('resource_id', $this->ids['resource'])->delete();
                foreach (['allocation_units' => 'unit', 'resources' => 'resource', 'facilities' => 'facility', 'centres' => 'centre'] as $table => $key) {
                    $db->table($table)->where('id', $this->ids[$key])->delete();
                }
                $db->table('model_has_permissions')->where('model_type', User::class)->where('model_id', $this->ids['customer'])->delete();
                $db->table('users')->where('id', $this->ids['customer'])->delete();
                if (isset($this->ids['permission'])) {
                    $db->table('permissions')->where('id', $this->ids['permission'])->delete();
                }
            }
        } finally {
            DB::disconnect(self::ConnectionName);
            parent::tearDown();
        }
    }

    public function test_simultaneous_initiation_creates_one_payment_and_one_provider_checkout(): void
    {
        $booking = $this->fixture();

        $outcomes = $this->runWorkers('initiate', $booking);

        $this->assertSame($outcomes[0]['payment_id'], $outcomes[1]['payment_id']);
        $this->assertSame(1, $this->connection()->table('payments')->where('booking_id', $booking->id)->count());
        $this->assertSame(1, $this->auditCount('payment.initiated', Payment::class));
        $this->assertSame(1, $this->auditCount('test.checkout_created', Payment::class));
        $persisted = $this->connection()->table('bookings')->find($booking->id);
        $this->assertSame('approved', $persisted->status);
        $this->assertSame('awaiting_payment', $persisted->financial_status);
    }

    public function test_simultaneous_duplicate_provider_events_confirm_and_audit_once(): void
    {
        $booking = $this->fixture();
        $payment = $this->onConnection(fn (): Payment => Payment::factory()->for($booking)->create([
            'customer_id' => $booking->customer_id,
            'provider_session_id' => 'cs_test_concurrent_'.$booking->id,
            'provider_payment_intent_id' => 'pi_concurrent_'.$booking->id,
            'session_expires_at' => now()->addHours(2),
        ]));
        $event = [
            'id' => 'evt_concurrent_'.$payment->id, 'type' => 'checkout.session.completed',
            'created' => now()->getTimestamp(), 'livemode' => false,
            'data' => ['object' => [
                'id' => $payment->provider_session_id, 'object' => 'checkout.session',
                'mode' => 'payment', 'livemode' => false,
                'metadata' => ['payment_reference' => $payment->reference],
                'client_reference_id' => $payment->reference,
                'amount_total' => 5000, 'currency' => 'gbp',
                'payment_intent' => $payment->provider_payment_intent_id,
                'status' => 'complete', 'payment_status' => 'paid',
            ]],
        ];

        $this->runWorkers('reconcile', $booking, $event);

        $this->assertSame('succeeded', $this->connection()->table('payments')->find($payment->id)->status);
        $persisted = $this->connection()->table('bookings')->find($booking->id);
        $this->assertSame('confirmed', $persisted->status);
        $this->assertSame('paid', $persisted->financial_status);
        $this->assertSame(1, $this->connection()->table('payment_provider_events')->where('payment_id', $payment->id)->count());
        $this->assertSame(1, $this->auditCount('payment.succeeded', Payment::class));
        $this->assertSame(1, $this->auditCount('booking.confirmed', Booking::class));
    }

    private function fixture(): Booking
    {
        return $this->onConnection(function (): Booking {
            $booking = Booking::factory()->create([
                'starts_at' => now()->addDays(4), 'ends_at' => now()->addDays(4)->addHours(2),
                'status' => 'approved', 'financial_status' => 'awaiting_payment', 'payment_due_at' => now()->addDay(),
            ]);
            $permission = Permission::query()->where('name', 'payments.initiate')->where('guard_name', 'web')->first();
            if ($permission === null) {
                $permission = Permission::create(['name' => 'payments.initiate', 'guard_name' => 'web']);
                $this->ids['permission'] = $permission->id;
            }
            $booking->customer->givePermissionTo($permission);
            $snapshot = BookingPriceSnapshot::factory()->for($booking)->create();
            $line = BookingPriceLine::factory()->for($snapshot, 'snapshot')->create();
            $unit = AllocationUnit::factory()->for($booking->resource->facility)->create();
            $booking->resource->syncAllocationUnits($unit);
            $occupancy = AllocationOccupancy::factory()->for($booking)->create([
                'starts_at' => $booking->starts_at, 'ends_at' => $booking->ends_at, 'expires_at' => null,
            ]);
            $occupancy->allocationUnits()->attach($unit);
            $this->ids += [
                'booking' => $booking->id, 'customer' => $booking->customer_id,
                'resource' => $booking->resource_id, 'facility' => $booking->facility_id,
                'centre' => $booking->centre_id, 'unit' => $unit->id, 'occupancy' => $occupancy->id,
                'snapshot' => $snapshot->id, 'line' => $line->id,
            ];

            return $booking;
        });
    }

    private function auditCount(string $event, string $subject): int
    {
        $ids = $subject === Booking::class ? [$this->ids['booking']]
            : $this->connection()->table('payments')->where('booking_id', $this->ids['booking'])->pluck('id');

        return $this->connection()->table('activity_log')->where('event', $event)->where('subject_type', $subject)->whereIn('subject_id', $ids)->count();
    }

    /**
     * @param  array<string, mixed>  $event
     * @return list<array{payment_id: int|null}>
     */
    private function runWorkers(string $operation, Booking $booking, array $event = []): array
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        $this->assertNotFalse($server, "Concurrency barrier failed: {$errorMessage} ({$errorCode}).");
        $address = stream_socket_get_name($server, false);
        $this->assertNotFalse($address);
        $workers = [];
        for ($index = 0; $index < 2; $index++) {
            $process = proc_open([
                PHP_BINARY, base_path('tests/Support/run-concurrent-payment.php'),
                "tcp://{$address}", $operation, (string) $booking->id, json_encode($event, JSON_THROW_ON_ERROR),
            ], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path(), [
                ...getenv(), 'APP_ENV' => 'testing', 'APP_KEY' => (string) config('app.key'),
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => (string) config('database.connections.mysql.host'),
                'DB_PORT' => (string) config('database.connections.mysql.port'),
                'DB_DATABASE' => (string) config('database.connections.mysql.database'),
                'DB_USERNAME' => (string) config('database.connections.mysql.username'),
                'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
            ]);
            $this->assertIsResource($process);
            fclose($pipes[0]);
            $workers[] = ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
        }
        try {
            $sockets = [];
            foreach ($workers as $worker) {
                $socket = stream_socket_accept($server, 15);
                $this->assertNotFalse($socket, 'Payment worker did not reach the barrier.');
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

    private function connection(): Connection
    {
        return DB::connection(self::ConnectionName);
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    private function onConnection(Closure $callback): mixed
    {
        $original = DB::getDefaultConnection();
        DB::setDefaultConnection(self::ConnectionName);
        try {
            return $callback();
        } finally {
            DB::setDefaultConnection($original);
        }
    }
}
