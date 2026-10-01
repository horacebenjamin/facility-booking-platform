<?php

namespace Tests\Feature\Models;

use App\Enums\EquipmentChargeType;
use App\Enums\PricingRateUnit;
use App\Models\Centre;
use App\Models\Equipment;
use App\Models\EquipmentRate;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\ResourceRate;
use Database\Seeders\VenueDevelopmentSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PricingRateTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_resource_rate_stores_money_as_exact_integer_minor_units(): void
    {
        $rate = ResourceRate::factory()->create(['amount_minor' => 2575]);

        $this->assertSame(2575, $rate->fresh()->amount_minor);
        $this->assertIsInt($rate->fresh()->amount_minor);
        $this->assertSame('GBP', $rate->fresh()->currency);
        $this->assertSame(PricingRateUnit::Hourly, $rate->fresh()->rate_unit);
    }

    public function test_resources_can_have_different_rates_at_different_centres(): void
    {
        $firstCentre = Centre::factory()->create();
        $secondCentre = Centre::factory()->create();
        $firstResource = Resource::factory()->for(Facility::factory()->for($firstCentre))->create();
        $secondResource = Resource::factory()->for(Facility::factory()->for($secondCentre))->create();

        ResourceRate::factory()->for($firstResource)->create(['amount_minor' => 1800]);
        ResourceRate::factory()->for($secondResource)->create(['amount_minor' => 4250]);

        $this->assertSame(1800, $firstResource->rates()->sole()->amount_minor);
        $this->assertSame(4250, $secondResource->rates()->sole()->amount_minor);
        $this->assertNotSame($firstResource->facility->centre_id, $secondResource->facility->centre_id);
    }

    public function test_effective_dates_are_persisted_with_inclusive_date_boundaries(): void
    {
        $rate = ResourceRate::factory()->create([
            'effective_from' => '2026-04-01',
            'effective_until' => '2026-04-30',
        ]);

        $rate = $rate->fresh();

        $this->assertSame('2026-04-01', $rate->effective_from->toDateString());
        $this->assertSame('2026-04-30', $rate->effective_until?->toDateString());
    }

    public function test_database_rejects_a_zero_resource_rate_amount(): void
    {
        $this->expectException(QueryException::class);

        ResourceRate::factory()->create(['amount_minor' => 0]);
    }

    public function test_database_rejects_lowercase_resource_rate_currency(): void
    {
        $this->expectException(QueryException::class);

        ResourceRate::factory()->create(['currency' => 'gbp']);
    }

    public function test_database_rejects_invalid_resource_rate_effective_periods(): void
    {
        $this->expectException(QueryException::class);

        ResourceRate::factory()->create([
            'effective_from' => '2026-05-02',
            'effective_until' => '2026-05-01',
        ]);
    }

    public function test_equipment_rates_can_be_included_or_separately_chargeable(): void
    {
        $includedRate = EquipmentRate::factory()->included()->create();
        $chargeableRate = EquipmentRate::factory()->create(['amount_minor' => 750]);

        $this->assertSame(EquipmentChargeType::Included, $includedRate->fresh()->charge_type);
        $this->assertSame(0, $includedRate->fresh()->amount_minor);
        $this->assertSame(EquipmentChargeType::SeparatelyChargeable, $chargeableRate->fresh()->charge_type);
        $this->assertSame(750, $chargeableRate->fresh()->amount_minor);
    }

    public function test_database_rejects_invalid_equipment_charge_amounts(): void
    {
        $this->expectException(QueryException::class);

        EquipmentRate::factory()->create([
            'charge_type' => EquipmentChargeType::Included,
            'amount_minor' => 100,
        ]);
    }

    public function test_database_rejects_invalid_equipment_rate_effective_periods(): void
    {
        $this->expectException(QueryException::class);

        EquipmentRate::factory()->create([
            'effective_from' => '2026-06-02',
            'effective_until' => '2026-06-01',
        ]);
    }

    public function test_resource_and_equipment_rates_prevent_deletion_of_their_configuration_subjects(): void
    {
        $resource = Resource::factory()->create();
        ResourceRate::factory()->for($resource)->create();
        $equipment = Equipment::factory()->create();
        EquipmentRate::factory()->for($equipment)->create();

        try {
            $resource->delete();
            $this->fail('A resource with pricing configuration must not be deleted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('resource_rates', ['resource_id' => $resource->id]);
        }

        try {
            $equipment->delete();
            $this->fail('Equipment with pricing configuration must not be deleted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('equipment_rates', ['equipment_id' => $equipment->id]);
        }
    }

    public function test_fictional_demo_rates_are_deterministic_and_valid(): void
    {
        $this->seed(VenueDevelopmentSeeder::class);
        $this->seed(VenueDevelopmentSeeder::class);

        $this->assertSame(10, ResourceRate::query()->count());
        $this->assertSame(8, EquipmentRate::query()->count());
        $this->assertSame(7200, ResourceRate::query()->whereHas('resource', fn ($query) => $query->where('slug', 'whole-sports-hall'))->sole()->amount_minor);
        $this->assertSame(EquipmentChargeType::Included, EquipmentRate::query()->whereHas('equipment', fn ($query) => $query->where('name', 'Badminton nets'))->sole()->charge_type);
        $this->assertSame('GBP', ResourceRate::query()->firstOrFail()->currency);
    }
}
