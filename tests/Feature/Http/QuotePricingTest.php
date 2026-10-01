<?php

namespace Tests\Feature\Http;

use App\Models\AllocationOccupancy;
use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use App\Models\EquipmentRate;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\ResourceRate;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class QuotePricingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_named_route_accepts_guest_post_requests(): void
    {
        $route = Route::getRoutes()->getByName('pricing.quote');

        $this->assertNotNull($route);
        $this->assertSame(['POST'], $route->methods());
        $this->assertSame('pricing/quote', $route->uri());
    }

    public function test_returns_a_customer_safe_quote_for_chargeable_equipment(): void
    {
        $resource = $this->pricedResource(2500);
        $equipment = Equipment::factory()->for($resource->facility->centre)->create([
            'name' => 'Portable PA system',
        ]);
        EquipmentRate::factory()->for($equipment)->create(['amount_minor' => 500]);

        $response = $this->postJson(route('pricing.quote'), $this->payload(
            $resource,
            '2026-10-05 18:00:00',
            '2026-10-05 19:30:00',
            [['equipment_id' => $equipment->id, 'quantity' => 3]],
        ));

        $response->assertOk()
            ->assertExactJson([
                'data' => [
                    'currency' => 'GBP',
                    'duration_seconds' => 5400,
                    'resource' => [
                        'name' => $resource->name,
                        'hourly_rate_minor' => 2500,
                        'rate_unit' => 'hour',
                        'amount_minor' => 3750,
                    ],
                    'equipment' => [[
                        'name' => 'Portable PA system',
                        'quantity' => 3,
                        'charge_type' => 'separately_chargeable',
                        'hourly_rate_minor' => 500,
                        'rate_unit' => 'hour',
                        'amount_minor' => 2250,
                    ]],
                    'subtotal_minor' => 6000,
                    'discount_minor' => null,
                    'calculated_total_minor' => 6000,
                    'final_total_minor' => 6000,
                ],
            ])
            ->assertDontSee('rate_id')
            ->assertDontSee('responsible_user')
            ->assertDontSee('effective_from');
    }

    public function test_returns_an_included_equipment_line_with_zero_amount(): void
    {
        $resource = $this->pricedResource(2500);
        $equipment = Equipment::factory()->for($resource->facility->centre)->create([
            'name' => 'Badminton nets',
        ]);
        EquipmentRate::factory()->for($equipment)->included()->create();

        $this->postJson(route('pricing.quote'), $this->payload(
            $resource,
            equipment: [['equipment_id' => $equipment->id, 'quantity' => 2]],
        ))
            ->assertOk()
            ->assertJsonPath('data.equipment.0.charge_type', 'included')
            ->assertJsonPath('data.equipment.0.amount_minor', 0)
            ->assertJsonPath('data.final_total_minor', 2500);
    }

    public function test_uses_effective_resource_rates_without_accepting_browser_price_authority(): void
    {
        $resource = Resource::factory()->for(Facility::factory())->create();
        ResourceRate::factory()->for($resource)->create([
            'amount_minor' => 2000,
            'effective_from' => '2026-01-01',
            'effective_until' => '2026-06-30',
        ]);
        ResourceRate::factory()->for($resource)->create([
            'amount_minor' => 2750,
            'effective_from' => '2026-07-01',
        ]);

        $this->postJson(route('pricing.quote'), array_merge(
            $this->payload($resource, '2026-07-01 10:00:00', '2026-07-01 11:00:00'),
            [
                'amount_minor' => 1,
                'calculated_total_minor' => 1,
                'currency' => 'EUR',
                'discount' => ['amount_minor' => 2000],
                'final_total_minor' => 1,
                'override' => ['adjusted_total_minor' => 1],
                'rate_id' => 1,
                'subtotal_minor' => 1,
                'user_id' => 1,
            ],
        ))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'amount_minor',
                'calculated_total_minor',
                'currency',
                'discount',
                'final_total_minor',
                'override',
                'rate_id',
                'subtotal_minor',
                'user_id',
            ]);

        $this->postJson(route('pricing.quote'), $this->payload(
            $resource,
            '2026-07-01 10:00:00',
            '2026-07-01 11:00:00',
        ))
            ->assertOk()
            ->assertJsonPath('data.final_total_minor', 2750);
    }

    public function test_returns_customer_safe_failures_for_missing_and_ambiguous_pricing(): void
    {
        $missingRateResource = Resource::factory()->for(Facility::factory())->create();
        $ambiguousRateResource = Resource::factory()->for(Facility::factory())->create();
        ResourceRate::factory()->for($ambiguousRateResource)->create([
            'effective_from' => '2026-01-01',
        ]);
        ResourceRate::factory()->for($ambiguousRateResource)->create([
            'effective_from' => '2026-06-01',
        ]);

        $this->postJson(route('pricing.quote'), $this->payload($missingRateResource))
            ->assertConflict()
            ->assertExactJson([
                'message' => 'Pricing is not available for this selection.',
            ])
            ->assertDontSee('missing_resource_rate');

        $this->postJson(route('pricing.quote'), $this->payload($ambiguousRateResource))
            ->assertConflict()
            ->assertExactJson([
                'message' => 'Pricing is not available for this selection.',
            ])
            ->assertDontSee('ambiguous_resource_rate');
    }

    public function test_rejects_malformed_and_duplicate_equipment_requests(): void
    {
        $resource = $this->pricedResource();
        $equipment = Equipment::factory()->for($resource->facility->centre)->create();

        $this->postJson(route('pricing.quote'), [
            'resource_id' => $resource->id,
            'starts_at' => 'not-a-date',
            'ends_at' => '2026-10-05 19:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');

        $this->postJson(route('pricing.quote'), $this->payload(
            $resource,
            equipment: [
                ['equipment_id' => $equipment->id, 'quantity' => 1],
                ['equipment_id' => $equipment->id, 'quantity' => 2],
            ],
        ))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('equipment.1.equipment_id');
    }

    public function test_is_read_only_and_repeated_requests_return_the_same_quote(): void
    {
        $resource = $this->pricedResource();
        $counts = [
            'activity_log' => 0,
            'allocation_occupancies' => AllocationOccupancy::count(),
            'equipment_allocations' => EquipmentAllocation::count(),
        ];
        $payload = $this->payload($resource);

        $firstResponse = $this->postJson(route('pricing.quote'), $payload);
        $secondResponse = $this->postJson(route('pricing.quote'), $payload);

        $firstResponse->assertOk();
        $secondResponse->assertOk()->assertExactJson($firstResponse->json());

        foreach ($counts as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
    }

    private function pricedResource(int $amountMinor = 2500): Resource
    {
        $resource = Resource::factory()->for(Facility::factory())->create();
        ResourceRate::factory()->for($resource)->create(['amount_minor' => $amountMinor]);

        return $resource;
    }

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipment
     * @return array{resource_id: int, starts_at: string, ends_at: string, equipment: list<array{equipment_id: int, quantity: int}>}
     */
    private function payload(
        Resource $resource,
        string $startsAt = '2026-10-05 18:00:00',
        string $endsAt = '2026-10-05 19:00:00',
        array $equipment = [],
    ): array {
        return [
            'resource_id' => $resource->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'equipment' => $equipment,
        ];
    }
}
