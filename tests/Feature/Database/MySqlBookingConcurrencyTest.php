<?php

namespace Tests\Feature\Database;

use App\Actions\CreateBookingRequest;
use App\Enums\DayOfWeek;
use App\Models\AllocationUnit;
use App\Models\Centre;
use App\Models\Equipment;
use App\Models\EquipmentRate;
use App\Models\Facility;
use App\Models\FacilityBookableHour;
use App\Models\Resource;
use App\Models\ResourceBookableHour;
use App\Models\ResourceRate;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\EquipmentRequirement;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MySqlBookingConcurrencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ConcurrentConnection = 'mysql_booking_concurrency';

    /**
     * @var list<string>
     */
    private const ConcurrentTables = [
        'allocation_occupancy_allocation_unit',
        'equipment_allocations',
        'allocation_occupancies',
        'booking_price_lines',
        'booking_price_snapshots',
        'booking_equipment',
        'bookings',
        'activity_log',
        'allocation_unit_resource',
        'equipment_rates',
        'resource_rates',
        'resource_bookable_hours',
        'facility_bookable_hours',
        'equipment',
        'allocation_units',
        'resources',
        'facilities',
        'centres',
        'users',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('mysql', DB::connection()->getDriverName());
        DB::select('select 1');
        config(['database.connections.'.self::ConcurrentConnection => config('database.connections.mysql')]);
    }

    protected function tearDown(): void
    {
        if (DB::getDefaultConnection() !== self::ConcurrentConnection) {
            $connection = DB::connection(self::ConcurrentConnection);
            $connection->statement('SET FOREIGN_KEY_CHECKS=0');

            foreach (self::ConcurrentTables as $table) {
                $connection->table($table)->delete();
            }

            $connection->statement('SET FOREIGN_KEY_CHECKS=1');
            $connection->disconnect();
        }

        parent::tearDown();
    }

    public function test_two_simultaneous_requests_for_the_same_resource_leave_one_complete_protection(): void
    {
        $fixture = $this->onConcurrentConnection(fn (): array => $this->bookableFixture());

        $this->onConcurrentConnection(function () use ($fixture): void {
            $this->assertInitiallyAvailable($fixture['resource']);
        });
        $outcomes = $this->submitConcurrently(
            $fixture['customer']->id,
            $fixture['resource']->id,
            $fixture['customer']->id,
            $fixture['resource']->id,
        );

        $this->assertExactlyOneSucceeded($outcomes);
        $this->assertSame(1, $this->countRows('bookings'));
        $this->assertSame(1, $this->countRows('allocation_occupancies'));
        $this->assertSame(1, $this->countRows('allocation_occupancy_allocation_unit'));
        $this->assertSame(0, $this->countRows('booking_equipment'));
        $this->assertSame(1, $this->countRows('booking_price_snapshots'));
        $this->assertSame(1, $this->countRows('booking_price_lines'));
        $this->assertSame(0, $this->countRows('equipment_allocations'));
    }

    public function test_simultaneous_whole_hall_and_court_requests_contend_for_the_shared_allocation_unit(): void
    {
        $fixture = $this->onConcurrentConnection(fn (): array => $this->parentChildFixture());

        $this->onConcurrentConnection(function () use ($fixture): void {
            $this->assertInitiallyAvailable($fixture['wholeHall']);
        });
        $this->onConcurrentConnection(function () use ($fixture): void {
            $this->assertInitiallyAvailable($fixture['court']);
        });
        $outcomes = $this->submitConcurrently(
            $fixture['customer']->id,
            $fixture['wholeHall']->id,
            $fixture['customer']->id,
            $fixture['court']->id,
        );

        $this->assertExactlyOneSucceeded($outcomes);
        $this->assertSame(1, $this->countRows('bookings'));
        $this->assertSame(1, $this->countRows('allocation_occupancies'));
        $this->assertSame(
            1,
            $this->connection()->table('allocation_occupancy_allocation_unit')
                ->where('allocation_unit_id', $fixture['sharedUnit']->id)
                ->count(),
        );
        $this->assertSame(1, $this->countRows('booking_price_snapshots'));
        $this->assertSame(1, $this->countRows('booking_price_lines'));
    }

    public function test_simultaneous_requests_cannot_oversell_equipment_and_the_loser_rolls_back_completely(): void
    {
        $fixture = $this->onConcurrentConnection(fn (): array => $this->equipmentFixture());
        $equipment = [['equipment_id' => $fixture['equipment']->id, 'quantity' => 3]];

        $this->onConcurrentConnection(function () use ($fixture): void {
            $this->assertInitiallyAvailable($fixture['firstResource'], $fixture['equipment'], 3);
        });
        $this->onConcurrentConnection(function () use ($fixture): void {
            $this->assertInitiallyAvailable($fixture['secondResource'], $fixture['equipment'], 3);
        });
        $outcomes = $this->submitConcurrently(
            $fixture['customer']->id,
            $fixture['firstResource']->id,
            $fixture['customer']->id,
            $fixture['secondResource']->id,
            $equipment,
            $equipment,
        );

        $this->assertExactlyOneSucceeded($outcomes);
        $this->assertSame(1, $this->countRows('bookings'));
        $this->assertSame(1, $this->countRows('booking_equipment'));
        $this->assertSame(1, $this->countRows('booking_price_snapshots'));
        $this->assertSame(2, $this->countRows('booking_price_lines'));
        $this->assertSame(1, $this->countRows('allocation_occupancies'));
        $this->assertSame(1, $this->countRows('equipment_allocations'));
        $this->assertSame(3, (int) $this->connection()->table('equipment_allocations')->sum('quantity'));
    }

    public function test_booking_submissions_lock_allocation_units_and_equipment_in_primary_key_order(): void
    {
        $fixture = $this->onConcurrentConnection(fn (): array => $this->equipmentFixture());
        $secondEquipment = $this->onConcurrentConnection(function () use ($fixture): Equipment {
            $equipment = Equipment::factory()->for($fixture['equipment']->centre)->create(['quantity' => 3]);
            EquipmentRate::factory()->for($equipment)->create();

            return $equipment;
        });
        $lockingQueries = [];

        $this->connection()->listen(function (QueryExecuted $query) use (&$lockingQueries): void {
            if (str_contains(strtolower($query->sql), 'for update')) {
                $lockingQueries[] = $query->sql;
            }
        });

        $this->onConcurrentConnection(function () use ($fixture, $secondEquipment): void {
            app(CreateBookingRequest::class)->handle(
                $fixture['customer'],
                $fixture['firstResource']->id,
                CarbonImmutable::parse('2026-10-05 18:00:00', config('app.timezone')),
                CarbonImmutable::parse('2026-10-05 19:30:00', config('app.timezone')),
                [
                    ['equipment_id' => $secondEquipment->id, 'quantity' => 1],
                    ['equipment_id' => $fixture['equipment']->id, 'quantity' => 1],
                ],
            );
        });

        $allocationUnitLock = collect($lockingQueries)->first(
            static fn (string $query): bool => str_contains($query, 'from `allocation_units`'),
        );
        $equipmentLock = collect($lockingQueries)->first(
            static fn (string $query): bool => str_contains($query, 'from `equipment`'),
        );

        $this->assertIsString($allocationUnitLock);
        $this->assertIsString($equipmentLock);
        $this->assertStringContainsString('order by `allocation_units`.`id` asc', $allocationUnitLock);
        $this->assertStringContainsString('order by `id` asc', $equipmentLock);
    }

    /**
     * @param  list<array{outcome: string, booking_id?: int}>  $outcomes
     */
    private function assertExactlyOneSucceeded(array $outcomes): void
    {
        $this->assertCount(2, $outcomes);
        $this->assertSame(1, collect($outcomes)->where('outcome', 'succeeded')->count());
        $this->assertSame(1, collect($outcomes)->where('outcome', 'unavailable')->count());
    }

    private function assertInitiallyAvailable(Resource $resource, ?Equipment $equipment = null, ?int $quantity = null): void
    {
        $requirements = $equipment === null || $quantity === null
            ? []
            : [new EquipmentRequirement($equipment, $quantity)];

        $availability = app(AvailabilityService::class)->check(
            $resource,
            CarbonImmutable::parse('2026-10-05 18:00:00', config('app.timezone')),
            CarbonImmutable::parse('2026-10-05 19:30:00', config('app.timezone')),
            $requirements,
            CarbonImmutable::parse('2026-10-02 12:00:00', config('app.timezone')),
        );

        $this->assertTrue($availability->isAvailable());
    }

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $firstEquipment
     * @param  list<array{equipment_id: int, quantity: int}>  $secondEquipment
     * @return list<array{outcome: string, booking_id?: int}>
     */
    private function submitConcurrently(
        int $firstCustomerId,
        int $firstResourceId,
        int $secondCustomerId,
        int $secondResourceId,
        array $firstEquipment = [],
        array $secondEquipment = [],
    ): array {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        $this->assertNotFalse($server, "Could not create the booking concurrency barrier: {$errorMessage} ({$errorCode}).");

        $barrierAddress = stream_socket_get_name($server, false);
        $this->assertNotFalse($barrierAddress);

        $firstProcess = $this->startWorker('first', $barrierAddress, $firstCustomerId, $firstResourceId, $firstEquipment);
        $secondProcess = $this->startWorker('second', $barrierAddress, $secondCustomerId, $secondResourceId, $secondEquipment);
        $workers = [$firstProcess, $secondProcess];

        $barrierSockets = [];

        foreach ($workers as $worker) {
            $socket = stream_socket_accept($server, 10);
            if ($socket === false) {
                $this->fail("A booking submission worker did not reach the synchronization barrier [{$barrierAddress}].\n".$this->workerDiagnostics($workers));
            }
            $this->assertSame("ready\n", fgets($socket));
            $barrierSockets[] = $socket;
        }

        foreach ($barrierSockets as $socket) {
            fwrite($socket, "go\n");
            fclose($socket);
        }

        fclose($server);

        return array_map(fn (array $worker): array => $this->finishWorker($worker), $workers);
    }

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipmentSelections
     * @return array{command: list<string>, identifier: string, process: resource, stdout: resource, stderr: resource}
     */
    private function startWorker(string $identifier, string $barrierAddress, int $customerId, int $resourceId, array $equipmentSelections): array
    {
        $command = [
            PHP_BINARY,
            base_path('tests/Support/run-concurrent-booking-submission.php'),
            $identifier,
            "tcp://{$barrierAddress}",
            (string) $customerId,
            (string) $resourceId,
            '2026-10-05 18:00:00',
            '2026-10-05 19:30:00',
            json_encode($equipmentSelections, JSON_THROW_ON_ERROR),
        ];
        $process = proc_open($command, [
            ['pipe', 'r'],
            ['pipe', 'w'],
            ['pipe', 'w'],
        ], $pipes, base_path(), $this->workerEnvironment());

        $this->assertIsResource($process);
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        return [
            'command' => $command,
            'identifier' => $identifier,
            'process' => $process,
            'stdout' => $pipes[1],
            'stderr' => $pipes[2],
        ];
    }

    /**
     * @param  array{command: list<string>, identifier: string, process: resource, stdout: resource, stderr: resource}  $worker
     * @return array{outcome: string, booking_id?: int}
     */
    private function finishWorker(array $worker): array
    {
        stream_set_blocking($worker['stdout'], true);
        stream_set_blocking($worker['stderr'], true);
        $stdout = stream_get_contents($worker['stdout']);
        $stderr = stream_get_contents($worker['stderr']);
        $status = proc_get_status($worker['process']);
        fclose($worker['stdout']);
        fclose($worker['stderr']);

        $exitCode = proc_close($worker['process']);
        $this->assertSame(0, $exitCode, $this->formatWorkerOutput($worker, $stdout, $stderr, $status, $exitCode));

        /** @var array{outcome: string, booking_id?: int} $result */
        $result = json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function workerEnvironment(): array
    {
        return [
            ...getenv(),
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => (string) config('database.connections.mysql.database'),
            'DB_USERNAME' => (string) config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
        ];
    }

    /**
     * @param  list<array{command: list<string>, identifier: string, process: resource, stdout: resource, stderr: resource}>  $workers
     */
    private function workerDiagnostics(array $workers): string
    {
        $diagnostics = [];

        foreach ($workers as $worker) {
            $stdout = stream_get_contents($worker['stdout']);
            $stderr = stream_get_contents($worker['stderr']);
            $status = proc_get_status($worker['process']);

            if ($status['running']) {
                proc_terminate($worker['process']);
            }

            fclose($worker['stdout']);
            fclose($worker['stderr']);
            $exitCode = proc_close($worker['process']);
            $diagnostics[] = $this->formatWorkerOutput($worker, $stdout, $stderr, $status, $exitCode);
        }

        return implode("\n", $diagnostics);
    }

    /**
     * @param  array{command: list<string>, identifier: string, process: resource, stdout: resource, stderr: resource}  $worker
     * @param  array{command: string, pid: int, running: bool, signaled: bool, stopped: bool, exitcode: int, termsig: int, stopsig: int}|null  $status
     */
    private function formatWorkerOutput(array $worker, string|false $stdout, string|false $stderr, ?array $status = null, ?int $exitCode = null): string
    {
        return sprintf(
            "Worker [%s]\nCommand: %s\nStatus: %s\nExit code: %s\nStdout: %s\nStderr: %s",
            $worker['identifier'],
            json_encode($worker['command'], JSON_THROW_ON_ERROR),
            json_encode($status ?? proc_get_status($worker['process']), JSON_THROW_ON_ERROR),
            $exitCode === null ? 'not collected' : (string) $exitCode,
            $stdout === false ? '<unreadable>' : $stdout,
            $stderr === false ? '<unreadable>' : $stderr,
        );
    }

    private function countRows(string $table): int
    {
        return $this->connection()->table($table)->count();
    }

    private function connection(): Connection
    {
        return DB::connection(self::ConcurrentConnection);
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    private function onConcurrentConnection(Closure $callback): mixed
    {
        $originalConnection = DB::getDefaultConnection();
        DB::setDefaultConnection(self::ConcurrentConnection);

        try {
            return $callback();
        } finally {
            DB::setDefaultConnection($originalConnection);
        }
    }

    /**
     * @return array{customer: User, resource: resource}
     */
    private function bookableFixture(): array
    {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->for($centre)->create();
        $resource = $this->createBookableResource($facility, 'Sports Hall');
        $unit = AllocationUnit::factory()->for($facility)->create(['code' => 'hall']);
        $resource->syncAllocationUnits($unit);

        return ['customer' => User::factory()->create(), 'resource' => $resource];
    }

    /**
     * @return array{customer: User, wholeHall: resource, court: resource, sharedUnit: AllocationUnit}
     */
    private function parentChildFixture(): array
    {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->for($centre)->create();
        $sharedUnit = AllocationUnit::factory()->for($facility)->create(['code' => 'court-a']);
        $otherUnit = AllocationUnit::factory()->for($facility)->create(['code' => 'court-b']);
        $wholeHall = $this->createBookableResource($facility, 'Whole Hall');
        $court = $this->createBookableResource($facility, 'Court A');
        $wholeHall->syncAllocationUnits($sharedUnit, $otherUnit);
        $court->syncAllocationUnits($sharedUnit);

        return compact('wholeHall', 'court', 'sharedUnit') + ['customer' => User::factory()->create()];
    }

    /**
     * @return array{customer: User, equipment: Equipment, firstResource: resource, secondResource: resource}
     */
    private function equipmentFixture(): array
    {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->for($centre)->create();
        $firstResource = $this->createBookableResource($facility, 'Studio One');
        $secondResource = $this->createBookableResource($facility, 'Studio Two');
        $firstResource->syncAllocationUnits(AllocationUnit::factory()->for($facility)->create(['code' => 'studio-one']));
        $secondResource->syncAllocationUnits(AllocationUnit::factory()->for($facility)->create(['code' => 'studio-two']));
        $equipment = Equipment::factory()->for($centre)->create(['quantity' => 3]);
        EquipmentRate::factory()->for($equipment)->create();

        return compact('equipment', 'firstResource', 'secondResource') + ['customer' => User::factory()->create()];
    }

    private function createBookableResource(Facility $facility, string $name): Resource
    {
        $resource = Resource::factory()->for($facility)->create([
            'name' => $name,
            'slug' => str($name)->slug(),
        ]);
        if (! $facility->bookableHours()->where('day_of_week', DayOfWeek::Monday)->exists()) {
            FacilityBookableHour::factory()->for($facility)->create([
                'day_of_week' => DayOfWeek::Monday,
                'opens_at' => '08:00:00',
                'closes_at' => '22:00:00',
            ]);
        }
        ResourceBookableHour::factory()->for($resource)->create([
            'day_of_week' => DayOfWeek::Monday,
            'opens_at' => '08:00:00',
            'closes_at' => '22:00:00',
        ]);
        ResourceRate::factory()->for($resource)->create();

        return $resource;
    }
}
