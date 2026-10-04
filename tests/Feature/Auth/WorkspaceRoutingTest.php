<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\SystemRoleSeeder;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class WorkspaceRoutingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemRoleSeeder::class);
    }

    #[TestWith(['customer', '/dashboard'])]
    #[TestWith(['leisure-assistant', '/operations'])]
    #[TestWith(['manager', '/management'])]
    public function test_login_and_authenticated_login_visits_resolve_to_the_authorized_workspace(string $role, string $destination): void
    {
        $user = $this->userWithRoles([$role]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect($destination);
        $this->assertAuthenticatedAs($user);
        $this->get($destination)->assertOk();
        $this->get(route('login'))->assertRedirect($destination);
    }

    #[TestWith([['customer', 'leisure-assistant'], '/operations'])]
    #[TestWith([['customer', 'manager', 'leisure-assistant'], '/management'])]
    public function test_multiple_roles_have_deterministic_landing_and_keep_customer_access(array $roles, string $destination): void
    {
        $user = $this->userWithRoles($roles);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect($destination);
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('bookings.index'))->assertOk();
    }

    public function test_customer_intended_booking_url_is_preserved(): void
    {
        $user = $this->userWithRoles(['customer']);
        $intended = route('bookings.index');

        $this->withSession(['url.intended' => $intended])
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect($intended);
    }

    public function test_intended_operations_schedule_is_preserved_and_inertia_uses_a_full_page_redirect(): void
    {
        $user = $this->userWithRoles(['leisure-assistant']);
        $intended = route('filament.operations.pages.today-schedule');

        $this->withSession(['url.intended' => $intended])->withHeader('X-Inertia', 'true')
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertConflict()->assertHeader('X-Inertia-Location', $intended);
    }

    public function test_staff_intended_customer_dashboard_redirects_once_to_operations(): void
    {
        $user = $this->userWithRoles(['leisure-assistant']);
        $this->withSession(['url.intended' => route('dashboard')])
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertRedirect('/operations');
        $this->get('/operations')->assertOk();
    }

    #[TestWith(['operations', ['manager', 'leisure-assistant', 'customer']])]
    #[TestWith(['management', ['manager', 'leisure-assistant', 'customer']])]
    public function test_filament_login_keeps_the_selected_panel(string $panelId, array $roles): void
    {
        $user = $this->userWithRoles($roles);
        $panel = Filament::getPanel($panelId);
        Filament::setCurrentPanel($panel);
        Filament::bootCurrentPanel();

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')->assertRedirect($panel->getUrl());
    }

    public function test_two_factor_response_uses_staff_workspace_and_preserves_json_contract(): void
    {
        $user = $this->userWithRoles(['leisure-assistant']);
        $this->actingAs($user);
        $request = Request::create('/two-factor-challenge', 'POST');
        $request->setUserResolver(fn (): User => $user);
        $request->setLaravelSession(session()->driver());

        $response = app(TwoFactorLoginResponse::class)->toResponse($request);
        $this->assertSame(url('/operations'), $response->headers->get('Location'));
        $request->headers->set('Accept', 'application/json');
        $this->assertSame(204, app(TwoFactorLoginResponse::class)->toResponse($request)->getStatusCode());
    }

    /** @param list<string> $roles */
    private function userWithRoles(array $roles): User
    {
        $user = User::factory()->create();
        $user->assignRole($roles);

        return $user;
    }
}
