<?php

namespace Tests\Feature\Http;

use App\Models\Centre;
use App\Models\Equipment;
use App\Models\EquipmentRate;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\ResourceRate;
use App\Models\User;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BookingReviewPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemRoleSeeder::class);
    }

    public function test_unauthenticated_customer_is_redirected_to_login_and_the_selection_is_preserved_as_the_intended_url(): void
    {
        $resource = Resource::factory()->for(Facility::factory())->create();
        $equipment = Equipment::factory()->for($resource->facility->centre)->create();
        $payload = $this->payload($resource, [['equipment_id' => $equipment->id, 'quantity' => 2]]);
        $url = route('bookings.review', $payload);

        $this->get($url)
            ->assertRedirect(route('login'));

        $this->assertReviewSelectionUrl((string) session('url.intended'), $payload);
    }

    public function test_authenticated_customer_receives_the_review_selection_identity_and_current_server_quote(): void
    {
        $fixture = $this->pricedFixture();
        $customer = $this->customer();

        $this->withoutVite()
            ->actingAs($customer)
            ->get(route('bookings.review', $this->payload(
                $fixture['resource'],
                [['equipment_id' => $fixture['equipment']->id, 'quantity' => 2]],
            )))
            ->assertInertia(fn (Assert $page) => $page
                ->component('bookings/Review')
                ->where('selection.resource_id', $fixture['resource']->id)
                ->where('selection.centre_name', 'Riverside Centre')
                ->where('selection.facility_name', 'Sports Hall')
                ->where('selection.resource_name', 'Whole Hall')
                ->where('selection.starts_at', '2026-10-05 18:00:00')
                ->where('selection.ends_at', '2026-10-05 19:30:00')
                ->where('selection.duration_seconds', 5400)
                ->where('selection.equipment', [[
                    'equipment_id' => $fixture['equipment']->id,
                    'name' => 'Badminton nets',
                    'quantity' => 2,
                ]])
                ->where('quote.currency', 'GBP')
                ->where('quote.total_minor', 9000)
                ->where('customer.name', $customer->name)
                ->where('customer.email', $customer->email),
            );
    }

    public function test_customer_login_resumes_the_preserved_review_selection(): void
    {
        $resource = Resource::factory()->for(Facility::factory())->create();
        $equipment = Equipment::factory()->for($resource->facility->centre)->create();
        $customer = $this->customer();
        $payload = $this->payload($resource, [['equipment_id' => $equipment->id, 'quantity' => 2]]);
        $url = route('bookings.review', $payload);

        $this->get($url)->assertRedirect(route('login'));

        $response = $this->post(route('login.store'), [
            'email' => $customer->email,
            'password' => 'password',
        ]);

        $response->assertRedirect();

        $this->assertReviewSelectionUrl((string) $response->headers->get('Location'), $payload);
    }

    public function test_review_ignores_forged_ownership_status_and_price_context(): void
    {
        $fixture = $this->pricedFixture();
        $customer = $this->customer();
        $otherCustomer = $this->customer();
        $payload = [
            ...$this->payload($fixture['resource']),
            'customer_id' => $otherCustomer->id,
            'status' => 'confirmed',
            'currency' => 'USD',
            'final_total_minor' => 1,
        ];

        $this->withoutVite()
            ->actingAs($customer)
            ->get(route('bookings.review', $payload))
            ->assertInertia(fn (Assert $page) => $page
                ->where('customer.name', $customer->name)
                ->where('customer.email', $customer->email)
                ->where('quote.currency', 'GBP')
                ->where('quote.total_minor', 7500)
                ->missing('status')
                ->missing('customer.id'),
            );
    }

    public function test_review_returns_to_availability_when_current_pricing_is_unavailable(): void
    {
        $resource = Resource::factory()->for(Facility::factory())->create();
        $payload = $this->payload($resource);

        $this->actingAs($this->customer())
            ->get(route('bookings.review', $payload))
            ->assertRedirect(route('availability.index', $payload))
            ->assertSessionHas(
                'bookingReviewError',
                'Pricing changed before review. Please check availability again.',
            );
    }

    public function test_authenticated_user_without_booking_permission_is_forbidden(): void
    {
        $resource = Resource::factory()->for(Facility::factory())->create();

        $this->actingAs(User::factory()->create())
            ->get(route('bookings.review', $this->payload($resource)))
            ->assertForbidden();
    }

    /**
     * @return array{resource: resource, equipment: Equipment}
     */
    private function pricedFixture(): array
    {
        $centre = Centre::factory()->create(['name' => 'Riverside Centre']);
        $facility = Facility::factory()->for($centre)->create(['name' => 'Sports Hall']);
        $resource = Resource::factory()->for($facility)->create(['name' => 'Whole Hall']);
        ResourceRate::factory()->for($resource)->create(['amount_minor' => 5000]);
        $equipment = Equipment::factory()->for($centre)->create(['name' => 'Badminton nets']);
        EquipmentRate::factory()->for($equipment)->create(['amount_minor' => 500]);

        return compact('resource', 'equipment');
    }

    private function customer(): User
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        return $customer;
    }

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipment
     * @return array{resource_id: int, starts_at: string, ends_at: string, equipment: list<array{equipment_id: int, quantity: int}>}
     */
    private function payload(Resource $resource, array $equipment = []): array
    {
        return [
            'resource_id' => $resource->id,
            'starts_at' => '2026-10-05 18:00:00',
            'ends_at' => '2026-10-05 19:30:00',
            'equipment' => $equipment,
        ];
    }

    /**
     * @param  array{resource_id: int, starts_at: string, ends_at: string, equipment: list<array{equipment_id: int, quantity: int}>}  $payload
     */
    private function assertReviewSelectionUrl(string $url, array $payload): void
    {
        $urlParts = parse_url($url);

        if ($urlParts === false) {
            $this->fail('The intended URL could not be parsed.');
        }

        $this->assertSame('/bookings/review', $urlParts['path'] ?? null);

        parse_str($urlParts['query'] ?? '', $query);

        $this->assertSame((string) $payload['resource_id'], $query['resource_id'] ?? null);
        $this->assertSame($payload['starts_at'], $query['starts_at'] ?? null);
        $this->assertSame($payload['ends_at'], $query['ends_at'] ?? null);

        foreach ($payload['equipment'] as $index => $equipment) {
            $this->assertSame((string) $equipment['equipment_id'], $query['equipment'][$index]['equipment_id'] ?? null);
            $this->assertSame((string) $equipment['quantity'], $query['equipment'][$index]['quantity'] ?? null);
        }
    }
}
