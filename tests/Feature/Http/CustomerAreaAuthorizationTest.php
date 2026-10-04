<?php

namespace Tests\Feature\Http;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CustomerAreaAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemRoleSeeder::class);
    }

    #[TestWith(['leisure-assistant', '/operations'])]
    #[TestWith(['manager', '/management'])]
    public function test_staff_only_users_are_redirected_from_dashboard_and_denied_customer_workflows(string $role, string $workspace): void
    {
        $staff = User::factory()->create();
        $staff->assignRole($role);
        $booking = Booking::factory()->create(['customer_id' => $staff->id]);
        $invoice = Invoice::factory()->create(['customer_id' => $staff->id]);
        $notification = $staff->notifications()->create(['id' => fake()->uuid(), 'type' => 'test', 'data' => ['title' => 'Private']]);
        $this->actingAs($staff);

        $this->get(route('dashboard'))->assertRedirect($workspace);
        $version = app(HandleInertiaRequests::class)->version(Request::create('/dashboard'));
        $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])->get(route('dashboard'))
            ->assertConflict()->assertHeader('X-Inertia-Location', $workspace);
        $this->flushHeaders();
        foreach (['bookings.index', 'bookings.review', 'invoices.index', 'notifications.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->get(route('bookings.payment.show', $booking))->assertForbidden();
        $this->post(route('bookings.payment.store', $booking))->assertForbidden();
        $this->get(route('invoices.show', $invoice))->assertForbidden();
        $this->patch(route('notifications.read', $notification->id))->assertForbidden();
        foreach (['bookings.store', 'bookings.recurring.preview', 'bookings.recurring.store'] as $route) {
            $this->post(route($route), [])->assertForbidden();
        }
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseEmpty('payments');
        $this->assertNull($notification->fresh()->read_at);
    }

    #[TestWith(['leisure-assistant', 'operations'])]
    #[TestWith(['manager', 'management'])]
    public function test_shared_staff_profile_uses_staff_workspace_navigation_and_keeps_shared_security(string $role, string $workspace): void
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get(route('profile.edit'))->assertInertia(fn (Assert $page): Assert => $page
            ->component('settings/Profile')->where('auth.canUseCustomerArea', false)
            ->where('auth.workspace', $workspace)->where('auth.workspaceUrl', '/'.$workspace));
        $this->withSession(['auth.password_confirmed_at' => time()])->get(route('security.edit'))->assertOk();
    }

    public function test_customer_retains_customer_routes_and_navigation(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page): Assert => $page
            ->component('Dashboard')->where('auth.canUseCustomerArea', true)->where('auth.workspace', 'customer'));
        $this->get(route('bookings.index'))->assertOk();
        $this->get(route('invoices.index'))->assertOk();
        $this->get(route('notifications.index'))->assertOk();
    }

    public function test_public_availability_and_pricing_remain_public(): void
    {
        $this->get(route('availability.index'))->assertOk();
        $this->postJson(route('availability.check'), [])->assertUnprocessable();
        $this->postJson(route('pricing.quote'), [])->assertUnprocessable();
    }

    public function test_roleless_account_cannot_use_customer_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertForbidden();
    }
}
