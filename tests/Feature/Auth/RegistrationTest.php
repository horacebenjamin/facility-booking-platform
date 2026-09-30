<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemRoleSeeder::class);

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register_with_the_customer_role(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'test@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('customer'));
    }

    public function test_public_registration_cannot_assign_staff_roles(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'manager',
            'roles' => ['leisure-assistant'],
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'test@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('customer'));
        $this->assertFalse($user->hasRole('manager'));
        $this->assertFalse($user->hasRole('leisure-assistant'));
    }

    public function test_registration_does_not_create_a_user_when_the_customer_role_is_not_provisioned(): void
    {
        /** @var Role $customerRole */
        $customerRole = Role::findByName('customer');
        $customerRole->delete();

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertInternalServerError();
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }
}
