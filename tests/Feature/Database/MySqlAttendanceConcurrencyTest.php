<?php

namespace Tests\Feature\Database;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MySqlAttendanceConcurrencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ConnectionName = 'mysql_attendance_concurrency';

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', DB::connection()->getDriverName());
        config(['database.connections.'.self::ConnectionName => config('database.connections.mysql')]);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->ids !== []) {
                $db = DB::connection(self::ConnectionName);
                $db->table('activity_log')->where('subject_type', Booking::class)->where('subject_id', $this->ids['booking'])->delete();
                $db->table('bookings')->where('id', $this->ids['booking'])->delete();
                $db->table('centre_user')->where('user_id', $this->ids['staff'])->delete();
                $db->table('model_has_roles')->where('model_type', User::class)->where('model_id', $this->ids['staff'])->delete();
                $db->table('model_has_permissions')->where('model_type', User::class)->where('model_id', $this->ids['staff'])->delete();
                if (isset($this->ids['created_role'])) {
                    $db->table('role_has_permissions')->where('role_id', $this->ids['created_role'])->delete();
                    $db->table('roles')->where('id', $this->ids['created_role'])->delete();
                }
                foreach (['created_bookings.view', 'created_attendance.manage'] as $key) {
                    if (isset($this->ids[$key])) {
                        $db->table('permissions')->where('id', $this->ids[$key])->delete();
                    }
                }
                foreach (['staff', 'customer'] as $key) {
                    $db->table('users')->where('id', $this->ids[$key])->delete();
                }
                foreach (['resources' => 'resource', 'facilities' => 'facility', 'centres' => 'centre'] as $table => $key) {
                    $db->table($table)->where('id', $this->ids[$key])->delete();
                }
            }
        } finally {
            DB::disconnect(self::ConnectionName);
            parent::tearDown();
        }
    }

    #[TestWith(['arrival', 'arrival', 'expected', '2026-10-05 17:00:00', '2026-10-05 17:00:00', 'arrived'])]
    #[TestWith(['complete', 'complete', 'arrived', '2026-10-05 18:10:00', '2026-10-05 18:10:00', 'completed'])]
    #[TestWith(['arrival', 'no-show', 'expected', '2026-10-05 17:59:59', '2026-10-05 18:00:00', null])]
    public function test_racing_actions_commit_one_state_and_one_audit(string $first, string $second, string $initial, string $firstTime, string $secondTime, ?string $expected): void
    {
        $original = DB::getDefaultConnection();
        DB::setDefaultConnection(self::ConnectionName);
        try {
            $booking = Booking::factory()->create(['starts_at' => '2026-10-05 17:00:00', 'ends_at' => '2026-10-05 18:00:00', 'status' => 'confirmed', 'financial_status' => 'paid', 'attendance_state' => $initial, 'arrived_at' => $initial === 'arrived' ? '2026-10-05 17:00:00' : null]);
            $booking->resource->update(['setup_minutes' => 15, 'cleanup_minutes' => 10]);
            $staff = User::factory()->create();
            $role = Role::findOrCreate('leisure-assistant', 'web');
            $createdIds = $role->wasRecentlyCreated ? ['created_role' => $role->id] : [];
            foreach (['bookings.view', 'attendance.manage'] as $name) {
                $permission = Permission::findOrCreate($name, 'web');
                if ($permission->wasRecentlyCreated) {
                    $createdIds['created_'.$name] = $permission->id;
                }
                $staff->givePermissionTo($permission);
            }
            $staff->assignRole('leisure-assistant');
            $staff->assignedCentres()->attach($booking->centre_id);
            $this->ids = [...$createdIds, 'booking' => $booking->id, 'staff' => $staff->id, 'customer' => $booking->customer_id, 'resource' => $booking->resource_id, 'facility' => $booking->facility_id, 'centre' => $booking->centre_id];
        } finally {
            DB::setDefaultConnection($original);
        }

        $results = $this->workers([$first, $second], [$firstTime, $secondTime]);

        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => $result['accepted'])));
        $db = DB::connection(self::ConnectionName);
        $result = $db->table('bookings')->find($this->ids['booking']);
        if ($expected === null) {
            $this->assertContains($result->attendance_state, ['arrived', 'no_show']);
        } else {
            $this->assertSame($expected, $result->attendance_state);
        }
        $this->assertSame('confirmed', $result->status);
        $this->assertSame('paid', $result->financial_status);
        $audit = $db->table('activity_log')->where('subject_type', Booking::class)->where('subject_id', $this->ids['booking'])->sole();
        $this->assertSame($this->ids['staff'], $audit->causer_id);
    }

    /** @param list<string> $operations @param list<string> $times @return list<array{accepted: bool}> */
    private function workers(array $operations, array $times): array
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        $this->assertNotFalse($server, "Barrier failed: {$errorMessage} ({$errorCode})");
        $address = stream_socket_get_name($server, false);
        $this->assertNotFalse($address);
        $workers = [];
        try {
            foreach ($operations as $index => $operation) {
                $process = proc_open([PHP_BINARY, base_path('tests/Support/run-concurrent-attendance.php'), "tcp://{$address}", $operation, (string) $this->ids['booking'], (string) $this->ids['staff'], $times[$index]], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path(), [...getenv(), 'APP_ENV' => 'testing', 'APP_KEY' => (string) config('app.key'), 'DB_CONNECTION' => 'mysql', 'DB_HOST' => (string) config('database.connections.mysql.host'), 'DB_PORT' => (string) config('database.connections.mysql.port'), 'DB_DATABASE' => (string) config('database.connections.mysql.database'), 'DB_USERNAME' => (string) config('database.connections.mysql.username'), 'DB_PASSWORD' => (string) config('database.connections.mysql.password')]);
                $this->assertIsResource($process);
                fclose($pipes[0]);
                $workers[] = ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
            }
            $sockets = [];
            foreach ($workers as $worker) {
                $socket = stream_socket_accept($server, 15);
                $this->assertNotFalse($socket, 'Worker did not reach barrier.');
                $this->assertSame("ready\n", fgets($socket));
                $sockets[] = $socket;
            }
            foreach ($sockets as $socket) {
                fwrite($socket, "go\n");
                fclose($socket);
            }
            $results = [];
            foreach ($workers as $worker) {
                $stdout = stream_get_contents($worker['stdout']);
                $stderr = stream_get_contents($worker['stderr']);
                fclose($worker['stdout']);
                fclose($worker['stderr']);
                $this->assertSame(0, proc_close($worker['process']), (string) $stderr);
                $results[] = json_decode((string) $stdout, true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
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
