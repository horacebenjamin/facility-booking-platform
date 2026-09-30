<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemRoleSeeder::class);
    }

    public function test_customer_cannot_access_the_staff_panels(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $this->actingAs($customer)->get('/management')->assertForbidden();
        $this->actingAs($customer)->get('/operations')->assertForbidden();
    }

    public function test_manager_can_access_management_but_not_operations(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $this->actingAs($manager)->get('/management')->assertOk();
        $this->actingAs($manager)->get('/operations')->assertForbidden();
    }

    public function test_leisure_assistant_can_access_operations_but_not_management(): void
    {
        $leisureAssistant = User::factory()->create();
        $leisureAssistant->assignRole('leisure-assistant');

        $this->actingAs($leisureAssistant)->get('/operations')->assertOk();
        $this->actingAs($leisureAssistant)->get('/management')->assertForbidden();
    }

    public function test_authenticated_users_without_a_staff_role_are_denied_direct_staff_panel_urls(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/management')->assertForbidden();
        $this->actingAs($user)->get('/operations')->assertForbidden();
    }

    public function test_unauthenticated_users_are_redirected_to_the_staff_panel_login_pages(): void
    {
        $this->get('/management')->assertRedirect(route('filament.management.auth.login'));
        $this->get('/operations')->assertRedirect(route('filament.operations.auth.login'));
    }
}
