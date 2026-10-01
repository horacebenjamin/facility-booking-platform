<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\User;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemRoleSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, list<string>>
     */
    private const ExpectedRolePermissions = [
        'customer' => [
            'bookings.view',
            'bookings.create',
            'bookings.amend',
            'bookings.cancel',
        ],
        'manager' => [
            'bookings.view',
            'bookings.create',
            'bookings.approve',
            'bookings.amend',
            'bookings.cancel',
            'attendance.manage',
            'payments.view',
            'payments.record',
            'payments.refund',
            'invoices.view',
            'invoices.manage',
            'facilities.manage',
            'closures.manage',
            'incidents.manage',
            'pricing.manage',
            'reports.view',
        ],
        'leisure-assistant' => [
            'bookings.view',
            'attendance.manage',
            'payments.record',
            'incidents.manage',
        ],
    ];

    public function test_provisions_the_expected_system_roles_and_permissions(): void
    {
        $this->seed(SystemRoleSeeder::class);

        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('permissions', 16);

        $this->assertDatabaseHas('roles', ['name' => 'customer', 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => 'manager', 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => 'leisure-assistant', 'guard_name' => 'web']);

        $this->assertSame([
            'attendance.manage',
            'bookings.amend',
            'bookings.approve',
            'bookings.cancel',
            'bookings.create',
            'bookings.view',
            'closures.manage',
            'facilities.manage',
            'incidents.manage',
            'invoices.manage',
            'invoices.view',
            'payments.record',
            'payments.refund',
            'payments.view',
            'pricing.manage',
            'reports.view',
        ], Permission::query()->orderBy('name')->pluck('name')->all());

        foreach (self::ExpectedRolePermissions as $roleName => $permissions) {
            $this->assertSame(
                collect($permissions)->sort()->values()->all(),
                Role::findByName($roleName)->permissions()->orderBy('name')->pluck('name')->all(),
            );
        }
    }

    public function test_assigning_a_role_grants_its_broad_capabilities(): void
    {
        $this->seed(SystemRoleSeeder::class);

        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $this->assertTrue($manager->can('bookings.approve'));
        $this->assertTrue($manager->can('payments.refund'));
        $this->assertTrue($manager->can('facilities.manage'));
        $this->assertTrue($manager->can('reports.view'));
    }

    public function test_customer_does_not_receive_staff_capabilities(): void
    {
        $this->seed(SystemRoleSeeder::class);

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $this->assertTrue($customer->can('bookings.create'));
        $this->assertFalse($customer->can('bookings.approve'));
        $this->assertFalse($customer->can('attendance.manage'));
        $this->assertFalse($customer->can('payments.record'));
        $this->assertFalse($customer->can('facilities.manage'));
    }

    public function test_provisioning_does_not_assign_roles_to_existing_users(): void
    {
        $user = User::factory()->create();

        $this->seed(SystemRoleSeeder::class);

        $this->assertSame(0, $user->roles()->count());
    }

    public function test_provisioning_restores_the_role_permission_mapping(): void
    {
        $this->seed(SystemRoleSeeder::class);

        /** @var Role $manager */
        $manager = Role::findByName('manager');
        $manager->revokePermissionTo('reports.view');

        $this->assertFalse($manager->hasPermissionTo('reports.view'));

        $this->seed(SystemRoleSeeder::class);

        $this->assertTrue(Role::findByName('manager')->hasPermissionTo('reports.view'));
    }

    public function test_provisioning_is_idempotent(): void
    {
        $this->seed(SystemRoleSeeder::class);
        $this->seed(SystemRoleSeeder::class);

        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('permissions', 16);
        $this->assertSame(4, Role::findByName('customer')->permissions()->count());
        $this->assertSame(16, Role::findByName('manager')->permissions()->count());
        $this->assertSame(4, Role::findByName('leisure-assistant')->permissions()->count());
        $this->assertSame(16, Permission::query()->count());
    }
}
