<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SystemRoleSeeder extends Seeder
{
    private const GuardName = 'web';

    /**
     * @var array<string, list<string>>
     */
    private const RolePermissions = [
        'customer' => [
            'payments.initiate',
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
            'pricing.manage',
            'closures.manage',
            'incidents.manage',
            'reports.view',
        ],
        'leisure-assistant' => [
            'bookings.view',
            'attendance.manage',
            'payments.record',
            'incidents.manage',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::RolePermissions as $permissions) {
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, self::GuardName);
            }
        }

        foreach (self::RolePermissions as $roleName => $permissions) {
            /** @var Role $role */
            $role = Role::findOrCreate($roleName, self::GuardName);

            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
