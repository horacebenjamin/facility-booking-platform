<?php

namespace Tests\Feature\Database;

use App\Actions\CreateBookingRequest;
use App\Models\AllocationUnit;
use App\Models\AvailabilityBlock;
use App\Models\AvailabilityBlockBookingImpact;
use App\Models\Booking;
use App\Models\Centre;
use App\Models\CustomerInvoiceTerms;
use App\Models\Facility;
use App\Models\FacilityBookableHour;
use App\Models\Payment;
use App\Models\Resource;
use App\Models\ResourceBookableHour;
use App\Models\ResourceRate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MySqlClosureConcurrencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ConnectionName = 'mysql_closure_concurrency';

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', DB::connection()->getDriverName());
        DB::select('select 1');
        config(['database.connections.'.self::ConnectionName => config('database.connections.mysql')]);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->ids !== []) {
                $db = DB::connection(self::ConnectionName);
                $bookingIds = $db->table('bookings')->where('resource_id', $this->ids['resource'])->pluck('id');
                $blockIds = $db->table('availability_blocks')->where('centre_id', $this->ids['centre'])->pluck('id');
                $impactIds = $db->table('availability_block_booking_impacts')->whereIn('availability_block_id', $blockIds)->pluck('id');
                foreach ([Booking::class => $bookingIds, AvailabilityBlock::class => $blockIds, AvailabilityBlockBookingImpact::class => $impactIds] as $type => $subjectIds) {
                    $db->table('activity_log')->where('subject_type', $type)->whereIn('subject_id', $subjectIds)->delete();
                }
                $db->table('availability_block_booking_impacts')->whereIn('id', $impactIds)->delete();
                $db->table('availability_blocks')->whereIn('id', $blockIds)->delete();
                $occupancyIds = $db->table('allocation_occupancies')->whereIn('booking_id', $bookingIds)->pluck('id');
                $db->table('allocation_occupancy_allocation_unit')->whereIn('allocation_occupancy_id', $occupancyIds)->delete();
                $db->table('allocation_occupancies')->whereIn('id', $occupancyIds)->delete();
                $db->table('booking_price_lines')->whereIn('booking_id', $bookingIds)->delete();
                $db->table('booking_price_snapshots')->whereIn('booking_id', $bookingIds)->delete();
                $paymentIds = $db->table('payments')->whereIn('booking_id', $bookingIds)->pluck('id');
                $db->table('activity_log')->where('subject_type', Payment::class)->whereIn('subject_id', $paymentIds)->delete();
                $db->table('payment_provider_events')->whereIn('payment_id', $paymentIds)->delete();
                $db->table('payments')->whereIn('id', $paymentIds)->delete();
                $db->table('bookings')->whereIn('id', $bookingIds)->delete();
                $db->table('customer_invoice_terms')->where('centre_id', $this->ids['centre'])->delete();
                $db->table('resource_rates')->where('resource_id', $this->ids['resource'])->delete();
                $db->table('resource_bookable_hours')->where('resource_id', $this->ids['resource'])->delete();
                $db->table('facility_bookable_hours')->where('facility_id', $this->ids['facility'])->delete();
                $db->table('allocation_unit_resource')->where('resource_id', $this->ids['resource'])->delete();
                $db->table('allocation_units')->where('id', $this->ids['unit'])->delete();
                $db->table('resources')->where('id', $this->ids['resource'])->delete();
                $db->table('centre_user')->where('user_id', $this->ids['manager'])->delete();
                $db->table('model_has_roles')->where('model_type', User::class)->where('model_id', $this->ids['manager'])->delete();
                $db->table('model_has_permissions')->where('model_type', User::class)->where('model_id', $this->ids['manager'])->delete();
                $db->table('facilities')->where('id', $this->ids['facility'])->delete();
                $db->table('centres')->where('id', $this->ids['centre'])->delete();
                $db->table('users')->whereIn('id', [$this->ids['manager'], $this->ids['customer']])->delete();
                if (isset($this->ids['created_role'])) {
                    $db->table('role_has_permissions')->where('role_id', $this->ids['created_role'])->delete();
                    $db->table('roles')->where('id', $this->ids['created_role'])->delete();
                }
                if (isset($this->ids['created_permission'])) {
                    $db->table('permissions')->where('id', $this->ids['created_permission'])->delete();
                }
                foreach (['created_view_permission', 'created_approve_permission'] as $key) {
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

    public function test_closure_committing_while_request_waits_for_centre_lock_refuses_request(): void
    {
        $this->fixture();

        $results = $this->workers('closure', 'booking');

        $this->assertSame('accepted', $results[0]['outcome']);
        $this->assertSame('unavailable', $results[1]['outcome']);
        $db = DB::connection(self::ConnectionName);
        $this->assertSame(0, $db->table('bookings')->where('resource_id', $this->ids['resource'])->count());
        $this->assertSame(0, $db->table('allocation_occupancies')->whereIn('booking_id', $db->table('bookings')->where('resource_id', $this->ids['resource'])->select('id'))->count());
    }

    public function test_request_committing_while_closure_waits_creates_one_active_requested_impact(): void
    {
        $this->fixture();

        $results = $this->workers('booking', 'closure');

        $this->assertSame('accepted', $results[0]['outcome']);
        $this->assertSame('accepted', $results[1]['outcome']);
        $db = DB::connection(self::ConnectionName);
        $booking = $db->table('bookings')->where('resource_id', $this->ids['resource'])->sole();
        $this->assertSame('requested', $booking->status);
        $this->assertSame('not_due', $booking->financial_status);
        $this->assertSame('expected', $booking->attendance_state);
        $this->assertGreaterThan('2026-10-01 12:00:00', $db->table('allocation_occupancies')->where('booking_id', $booking->id)->sole()->expires_at);
        $impact = $db->table('availability_block_booking_impacts')->where('booking_id', $booking->id)->sole();
        $this->assertSame($results[1]['block_id'], $impact->availability_block_id);
        $this->assertSame('unresolved', $impact->status);
    }

    public function test_concurrent_detection_preserves_existing_impact_and_creates_no_duplicate_audit(): void
    {
        $this->fixture();
        $original = DB::getDefaultConnection();
        DB::setDefaultConnection(self::ConnectionName);
        try {
            $booking = Booking::factory()->create(['resource_id' => $this->ids['resource'], 'customer_id' => $this->ids['customer'], 'status' => 'confirmed', 'financial_status' => 'paid', 'starts_at' => '2026-10-05 18:00:00', 'ends_at' => '2026-10-05 19:00:00']);
            $block = AvailabilityBlock::factory()->forCentre(Centre::findOrFail($this->ids['centre']))->create(['starts_at' => '2026-10-05 17:00:00', 'ends_at' => '2026-10-05 20:00:00']);
            $this->ids['block'] = $block->id;
            $resolvedBooking = Booking::factory()->create(['resource_id' => $this->ids['resource'], 'customer_id' => $this->ids['customer'], 'status' => 'confirmed', 'financial_status' => 'paid', 'starts_at' => '2026-10-05 19:00:00', 'ends_at' => '2026-10-05 20:00:00']);
            $resolved = AvailabilityBlockBookingImpact::factory()->create([
                'availability_block_id' => $block->id, 'booking_id' => $resolvedBooking->id,
                'detected_at' => '2026-09-30 09:00:00', 'status' => 'resolved',
                'resolved_at' => '2026-09-30 10:00:00', 'resolved_by' => $this->ids['manager'],
            ]);
            $resolvedBefore = (array) DB::table('availability_block_booking_impacts')->find($resolved->id);
        } finally {
            DB::setDefaultConnection($original);
        }

        $results = $this->workers('detect', 'detect');

        $this->assertSame(1, $results[0]['detected_count']);
        $this->assertSame(0, $results[1]['detected_count']);
        $db = DB::connection(self::ConnectionName);
        $impact = $db->table('availability_block_booking_impacts')->where('booking_id', $booking->id)->sole();
        $this->assertSame('unresolved', $impact->status);
        $this->assertSame('2026-10-01 12:00:00', $impact->detected_at);
        $this->assertNull($impact->resolved_at);
        $this->assertSame(1, $db->table('activity_log')->where('subject_type', AvailabilityBlockBookingImpact::class)->where('subject_id', $impact->id)->where('event', 'closure.booking_affected')->count());
        $this->assertSame('confirmed', $db->table('bookings')->find($booking->id)->status);
        $this->assertSame('paid', $db->table('bookings')->find($booking->id)->financial_status);
        $this->assertSame($resolvedBefore, (array) $db->table('availability_block_booking_impacts')->find($resolved->id));
        $this->assertSame(2, $db->table('availability_block_booking_impacts')->where('availability_block_id', $block->id)->count());
    }

    public function test_concurrent_resolution_accepts_once_and_preserves_booking_and_financial_state(): void
    {
        $this->fixture();
        $original = DB::getDefaultConnection();
        DB::setDefaultConnection(self::ConnectionName);
        try {
            $booking = Booking::factory()->create([
                'resource_id' => $this->ids['resource'], 'customer_id' => $this->ids['customer'],
                'status' => 'confirmed', 'financial_status' => 'paid',
                'starts_at' => '2026-10-05 18:00:00', 'ends_at' => '2026-10-05 19:00:00',
            ]);
            $block = AvailabilityBlock::factory()->forCentre(Centre::findOrFail($this->ids['centre']))->create([
                'starts_at' => '2026-10-05 17:00:00', 'ends_at' => '2026-10-05 20:00:00',
            ]);
            $impact = AvailabilityBlockBookingImpact::factory()->create([
                'availability_block_id' => $block->id, 'booking_id' => $booking->id,
                'operational_requirement' => 'no_action_required', 'financial_requirement' => 'not_required',
                'communication_required' => false, 'review_notes' => 'Closure no longer needs action',
            ]);
            $this->ids['impact'] = $impact->id;
            $bookingBefore = (array) DB::table('bookings')->find($booking->id);
        } finally {
            DB::setDefaultConnection($original);
        }

        $results = $this->workers('resolve', 'resolve');

        $this->assertSame('accepted', $results[0]['outcome']);
        $this->assertSame('rejected', $results[1]['outcome']);
        $db = DB::connection(self::ConnectionName);
        $resolved = $db->table('availability_block_booking_impacts')->find($impact->id);
        $this->assertSame('resolved', $resolved->status);
        $this->assertSame($this->ids['manager'], $resolved->resolved_by);
        $this->assertSame('2026-10-01 12:00:00', $resolved->resolved_at);
        $this->assertSame('First manager resolution', $resolved->resolution_notes);
        $this->assertSame($bookingBefore, (array) $db->table('bookings')->find($booking->id));
        $audit = $db->table('activity_log')->where('subject_type', AvailabilityBlockBookingImpact::class)
            ->where('subject_id', $impact->id)->where('event', 'closure.impact_resolved')->sole();
        $this->assertSame($this->ids['manager'], $audit->causer_id);
    }

    #[TestWith(['closure', 'approve', 'requested'])]
    #[TestWith(['approve', 'closure', 'confirmed'])]
    #[TestWith(['closure', 'confirm', 'confirmed'])]
    #[TestWith(['confirm', 'closure', 'confirmed'])]
    public function test_closure_and_confirmation_serialize_and_preserve_explicit_impact(string $first, string $second, string $expectedStatus): void
    {
        $this->fixture();
        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00', config('app.timezone')));
        $original = DB::getDefaultConnection();
        DB::setDefaultConnection(self::ConnectionName);
        try {
            $booking = app(CreateBookingRequest::class)->handle(User::findOrFail($this->ids['customer']), $this->ids['resource'],
                CarbonImmutable::parse('2026-10-05 18:00:00', config('app.timezone')), CarbonImmutable::parse('2026-10-05 19:00:00', config('app.timezone')));
            $this->ids['booking'] = $booking->id;
            $this->ids['block'] = $booking->id;
            if ($first === 'confirm' || $second === 'confirm') {
                $booking->update(['status' => 'approved', 'financial_status' => 'awaiting_payment', 'payment_due_at' => now()->addDay()]);
                $booking->allocationOccupancy()->update(['expires_at' => null]);
                Payment::factory()->for($booking)->create(['amount_minor' => $booking->priceSnapshot->final_total_minor]);
            } else {
                CustomerInvoiceTerms::create(['customer_id' => $booking->customer_id, 'centre_id' => $booking->centre_id, 'enabled' => true, 'term_days' => 30, 'authorised_by' => $this->ids['manager'], 'authorised_at' => now()]);
            }
        } finally {
            DB::setDefaultConnection($original);
        }

        $results = $this->workers($first, $second);

        $this->assertSame('accepted', $results[0]['outcome']);
        $this->assertSame($first === 'closure' && $second === 'approve' ? 'unavailable' : 'accepted', $results[1]['outcome']);
        $db = DB::connection(self::ConnectionName);
        $persisted = $db->table('bookings')->find($booking->id);
        $this->assertSame($expectedStatus, $persisted->status);
        $this->assertSame($expectedStatus === 'requested' ? 'not_due' : (($first === 'confirm' || $second === 'confirm') ? 'paid' : 'invoice_outstanding'), $persisted->financial_status);
        $this->assertSame('expected', $persisted->attendance_state);
        $this->assertSame('unresolved', $db->table('availability_block_booking_impacts')->where('booking_id', $booking->id)->sole()->status);
    }

    private function fixture(): void
    {
        $original = DB::getDefaultConnection();
        DB::setDefaultConnection(self::ConnectionName);
        try {
            $centre = Centre::factory()->create();
            $facility = Facility::factory()->for($centre)->create();
            $resource = Resource::factory()->for($facility)->create();
            $unit = AllocationUnit::factory()->for($facility)->create();
            $resource->syncAllocationUnits($unit);
            FacilityBookableHour::factory()->for($facility)->create(['day_of_week' => 1, 'opens_at' => '08:00:00', 'closes_at' => '22:00:00']);
            ResourceBookableHour::factory()->for($resource)->create(['day_of_week' => 1, 'opens_at' => '08:00:00', 'closes_at' => '22:00:00']);
            ResourceRate::factory()->for($resource)->create();
            $customer = User::factory()->create();
            $manager = User::factory()->create();
            $role = Role::findOrCreate('manager', 'web');
            $permission = Permission::findOrCreate('closures.manage', 'web');
            $viewPermission = Permission::findOrCreate('bookings.view', 'web');
            $approvePermission = Permission::findOrCreate('bookings.approve', 'web');
            $manager->assignRole($role);
            $manager->givePermissionTo($permission);
            $manager->givePermissionTo($viewPermission, $approvePermission);
            $manager->assignedCentres()->attach($centre);
            $this->ids = ['centre' => $centre->id, 'facility' => $facility->id, 'resource' => $resource->id, 'unit' => $unit->id, 'customer' => $customer->id, 'manager' => $manager->id];
            if ($role->wasRecentlyCreated) {
                $this->ids['created_role'] = $role->id;
            }
            if ($permission->wasRecentlyCreated) {
                $this->ids['created_permission'] = $permission->id;
            }
            if ($viewPermission->wasRecentlyCreated) {
                $this->ids['created_view_permission'] = $viewPermission->id;
            }
            if ($approvePermission->wasRecentlyCreated) {
                $this->ids['created_approve_permission'] = $approvePermission->id;
            }
        } finally {
            DB::setDefaultConnection($original);
        }
    }

    /** @return list<array<string, mixed>> */
    private function workers(string $firstOperation, string $secondOperation): array
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        $this->assertNotFalse($server, "Barrier failed: {$errorMessage} ({$errorCode})");
        $address = stream_socket_get_name($server, false);
        $this->assertNotFalse($address);
        $workers = [];
        $sockets = [];
        try {
            foreach ([$firstOperation, $secondOperation] as $index => $operation) {
                $actor = $operation === 'booking' ? $this->ids['customer'] : $this->ids['manager'];
                $process = proc_open([PHP_BINARY, base_path('tests/Support/run-concurrent-closure.php'), "tcp://{$address}", $operation, (string) $actor, (string) $this->ids['resource'], (string) $this->ids['centre'], (string) ($operation === 'resolve' ? $this->ids['impact'] : ($this->ids['block'] ?? 0)), $index === 0 ? 'hold' : 'free'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path(), [...getenv(), 'APP_ENV' => 'testing', 'APP_KEY' => (string) config('app.key'), 'DB_CONNECTION' => 'mysql', 'DB_HOST' => (string) config('database.connections.mysql.host'), 'DB_PORT' => (string) config('database.connections.mysql.port'), 'DB_DATABASE' => (string) config('database.connections.mysql.database'), 'DB_USERNAME' => (string) config('database.connections.mysql.username'), 'DB_PASSWORD' => (string) config('database.connections.mysql.password')]);
                $this->assertIsResource($process);
                fclose($pipes[0]);
                $workers[] = ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
                $socket = stream_socket_accept($server, 15);
                $this->assertNotFalse($socket, 'Worker did not reach barrier.');
                stream_set_timeout($socket, 20);
                $sockets[] = $socket;
                $ready = json_decode((string) fgets($socket), true, flags: JSON_THROW_ON_ERROR);
                fwrite($socket, "go\n");
                $this->assertSame("locking\n", fgets($socket));
                if ($index === 0) {
                    $this->assertSame("locked\n", fgets($socket));
                } else {
                    $this->waitForBlockedCentreQuery((int) $ready['connection_id']);
                    fwrite($sockets[0], "commit\n");
                    $this->assertSame("locked\n", fgets($socket));
                }
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
            foreach ($sockets as $socket) {
                fclose($socket);
            }
            fclose($server);
            foreach ($workers as $worker) {
                if (is_resource($worker['process'])) {
                    proc_terminate($worker['process']);
                    proc_close($worker['process']);
                }
            }
        }
    }

    private function waitForBlockedCentreQuery(int $connectionId): void
    {
        $deadline = microtime(true) + 10;
        do {
            foreach (DB::connection(self::ConnectionName)->select('SHOW FULL PROCESSLIST') as $process) {
                if ((int) $process->Id === $connectionId && is_string($process->Info)
                    && str_contains(strtolower($process->Info), 'centres')
                    && str_contains(strtolower($process->Info), 'for update')) {
                    return;
                }
            }
            usleep(10000);
        } while (microtime(true) < $deadline);

        $this->fail('The second worker did not enter a centre locking query while the first held the centre lock.');
    }
}
