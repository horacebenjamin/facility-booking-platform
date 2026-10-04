<?php

namespace Tests\Feature\Database;

use App\Actions\IssueInvoice;
use App\Models\AllocationOccupancy;
use App\Models\AllocationUnit;
use App\Models\Booking;
use App\Models\BookingPriceLine;
use App\Models\BookingPriceSnapshot;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MySqlInvoiceConcurrencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ConnectionName = 'mysql_invoice_concurrency';

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
                $invoiceIds = $db->table('invoices')->where('customer_id', $this->ids['customer'])->pluck('id');
                $paymentIds = $db->table('payments')->whereIn('invoice_id', $invoiceIds)->pluck('id');
                $db->table('payment_provider_events')->whereIn('payment_id', $paymentIds)->delete();
                $db->table('activity_log')->where('subject_type', Booking::class)->where('subject_id', $this->ids['booking'])->delete();
                $db->table('activity_log')->where('subject_type', Invoice::class)->whereIn('subject_id', $invoiceIds)->delete();
                $db->table('activity_log')->where('subject_type', Payment::class)->whereIn('subject_id', $paymentIds)->delete();
                $db->table('payments')->whereIn('invoice_id', $invoiceIds)->delete();
                $db->table('invoice_lines')->whereIn('invoice_id', $invoiceIds)->delete();
                $db->table('invoices')->whereIn('id', $invoiceIds)->delete();
                if (isset($this->ids['second_booking'])) {
                    $db->table('activity_log')->where('subject_type', Booking::class)->where('subject_id', $this->ids['second_booking'])->delete();
                    $db->table('booking_price_lines')->where('booking_id', $this->ids['second_booking'])->delete();
                    $db->table('booking_price_snapshots')->where('booking_id', $this->ids['second_booking'])->delete();
                    $db->table('bookings')->where('id', $this->ids['second_booking'])->delete();
                }
                $db->table('centre_user')->where('user_id', $this->ids['manager'])->delete();
                $db->table('model_has_permissions')->where('model_type', User::class)->where('model_id', $this->ids['manager'])->delete();
                $db->table('users')->where('id', $this->ids['manager'])->delete();
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
                foreach (['permission_invoices.manage', 'permission_payments.record'] as $key) {
                    if (isset($this->ids[$key])) {
                        $db->table('permissions')->where('id', $this->ids[$key])->delete();
                    }
                }
            }
        } finally {
            DB::disconnect(self::ConnectionName);
            parent::tearDown();
        }
    }

    public function test_simultaneous_invoice_issue_creates_one_invoice_and_one_charge(): void
    {
        $booking = $this->fixture();
        $outcomes = $this->runWorkers('issue', $booking);
        $this->assertSame(1, count(array_filter($outcomes, fn (array $result): bool => $result['accepted'])));
        $this->assertSame(1, $this->connection()->table('invoice_lines')->where('booking_id', $booking->id)->count());
        $this->assertSame(1, $this->connection()->table('invoices')->where('customer_id', $booking->customer_id)->count());
        $this->assertSame('confirmed', $this->connection()->table('bookings')->find($booking->id)->status);
        $this->assertSame('invoiced', $this->connection()->table('bookings')->find($booking->id)->financial_status);
        $this->assertSame(1, $this->connection()->table('activity_log')->where('event', 'invoice.issued')
            ->where('subject_type', Invoice::class)->whereIn('subject_id', $this->connection()->table('invoices')->where('customer_id', $booking->customer_id)->pluck('id'))->count());
    }

    public function test_simultaneous_manual_settlement_records_money_and_audit_once(): void
    {
        $booking = $this->fixture();
        $invoice = $this->onConnection(fn (): Invoice => app(IssueInvoice::class)->handle(User::findOrFail($this->ids['manager']), [$booking->id]));
        $outcomes = $this->runWorkers('settle', $booking, ['invoice_id' => $invoice->id]);
        $this->assertSame(1, count(array_filter($outcomes, fn (array $result): bool => $result['accepted'])));
        $this->assertSame(1, $this->connection()->table('payments')->where('invoice_id', $invoice->id)->count());
        $this->assertSame('paid', $this->connection()->table('invoices')->find($invoice->id)->status);
        $this->assertSame('paid', $this->connection()->table('bookings')->find($booking->id)->financial_status);
        $this->assertSame('confirmed', $this->connection()->table('bookings')->find($booking->id)->status);
        $this->assertSame(1, $this->connection()->table('activity_log')->where('event', 'invoice.paid')->where('subject_type', Invoice::class)->where('subject_id', $invoice->id)->count());
        $this->assertSame(1, $this->connection()->table('activity_log')->where('event', 'payment.manual_recorded')
            ->where('subject_type', Payment::class)->whereIn('subject_id', $this->connection()->table('payments')->where('invoice_id', $invoice->id)->pluck('id'))->count());
    }

    public function test_reversed_multi_booking_invoice_requests_use_consistent_lock_order(): void
    {
        $first = $this->fixture();
        $second = $this->onConnection(function () use ($first): Booking {
            $booking = Booking::factory()->create([
                'customer_id' => $first->customer_id, 'resource_id' => $first->resource_id,
                'status' => 'confirmed', 'financial_status' => 'invoice_outstanding',
                'billing_method' => 'invoice', 'invoice_term_days' => 30, 'payment_due_at' => null,
            ]);
            $snapshot = BookingPriceSnapshot::factory()->for($booking)->create();
            BookingPriceLine::factory()->for($snapshot, 'snapshot')->create();
            $this->ids['second_booking'] = $booking->id;

            return $booking;
        });
        $results = $this->runWorkers('issue', $first, ['booking_ids' => [$first->id, $second->id]]);
        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => $result['accepted'])));
        $this->assertSame(1, $this->connection()->table('invoices')->where('customer_id', $first->customer_id)->count());
        $this->assertSame(2, $this->connection()->table('invoice_lines')->whereIn('booking_id', [$first->id, $second->id])->count());
    }

    private function fixture(): Booking
    {
        return $this->onConnection(function (): Booking {
            $booking = Booking::factory()->create([
                'starts_at' => now()->addDays(4), 'ends_at' => now()->addDays(4)->addHours(2),
                'status' => 'confirmed', 'financial_status' => 'invoice_outstanding', 'payment_due_at' => null,
                'billing_method' => 'invoice', 'invoice_term_days' => 30,
            ]);
            $manager = User::factory()->create();
            $manager->assignedCentres()->attach($booking->centre_id);
            foreach (['invoices.manage', 'payments.record'] as $permissionName) {
                $permission = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
                if ($permission->wasRecentlyCreated) {
                    $this->ids['permission_'.$permissionName] = $permission->id;
                }
                $manager->givePermissionTo($permission);
            }
            $this->ids['manager'] = $manager->id;
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

    /**
     * @param  array<string, mixed>  $event
     * @return list<array{accepted: bool}>
     */
    private function runWorkers(string $operation, Booking $booking, array $event = []): array
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        $this->assertNotFalse($server, "Concurrency barrier failed: {$errorMessage} ({$errorCode}).");
        $address = stream_socket_get_name($server, false);
        $this->assertNotFalse($address);
        $workers = [];
        for ($index = 0; $index < 2; $index++) {
            $workerContext = $event;
            if ($index === 1 && isset($workerContext['booking_ids'])) {
                $workerContext['booking_ids'] = array_reverse($workerContext['booking_ids']);
            }
            $process = proc_open([
                PHP_BINARY, base_path('tests/Support/run-concurrent-invoice.php'),
                "tcp://{$address}", $operation, (string) $booking->id, json_encode([...$workerContext, 'manager_id' => $this->ids['manager']], JSON_THROW_ON_ERROR),
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
