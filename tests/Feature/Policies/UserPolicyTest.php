<?php

namespace Tests\Feature\Policies;

use App\Models\User;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemRoleSeeder::class);
    }

    public function test_user_policy_allows_users_to_manage_their_own_profile(): void
    {
        $user = User::factory()->create();

        $gate = Gate::forUser($user);

        $this->assertTrue($gate->allows('view', $user));
        $this->assertTrue($gate->allows('update', $user));
        $this->assertTrue($gate->allows('delete', $user));
    }

    public function test_user_policy_denies_users_access_to_another_profile(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $gate = Gate::forUser($user);

        $this->assertFalse($gate->allows('view', $otherUser));
        $this->assertFalse($gate->allows('update', $otherUser));
        $this->assertFalse($gate->allows('delete', $otherUser));
    }

    public function test_broad_staff_capabilities_are_available_through_laravel_gate(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $this->assertTrue(Gate::forUser($manager)->allows('reports.view'));
    }

    public function test_customer_cannot_use_a_representative_staff_capability(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $this->assertFalse(Gate::forUser($customer)->allows('reports.view'));
    }

    public function test_manager_role_does_not_bypass_missing_broad_capability(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        /** @var Role $managerRole */
        $managerRole = Role::findByName('manager');
        $managerRole->revokePermissionTo('reports.view');

        $this->assertFalse(Gate::forUser($manager)->allows('reports.view'));
    }
}
