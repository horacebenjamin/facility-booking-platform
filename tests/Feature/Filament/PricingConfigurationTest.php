<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\EquipmentRates\EquipmentRateResource;
use App\Filament\Resources\EquipmentRates\Pages\CreateEquipmentRate;
use App\Filament\Resources\ResourceRates\Pages\CreateResourceRate;
use App\Filament\Resources\ResourceRates\ResourceRateResource;
use App\Models\Centre;
use App\Models\Equipment;
use App\Models\EquipmentRate;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\ResourceRate;
use App\Models\User;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class PricingConfigurationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemRoleSeeder::class);
    }

    public function test_only_a_manager_with_pricing_permission_can_access_pricing_configuration(): void
    {
        $manager = $this->manager();
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $this->actingAs($manager)->get(ResourceRateResource::getUrl())->assertOk();
        $this->actingAs($customer)->get(ResourceRateResource::getUrl())->assertForbidden();
        $this->actingAs($customer)->get(EquipmentRateResource::getUrl())->assertForbidden();
    }

    public function test_manager_can_only_view_and_manage_rates_for_assigned_centres(): void
    {
        $manager = $this->manager();
        $assignedCentre = Centre::factory()->create();
        $otherCentre = Centre::factory()->create();
        $manager->assignedCentres()->attach($assignedCentre);
        $assignedResource = Resource::factory()->for(Facility::factory()->for($assignedCentre))->create();
        $otherResource = Resource::factory()->for(Facility::factory()->for($otherCentre))->create();
        $assignedEquipment = Equipment::factory()->for($assignedCentre)->create();
        $otherEquipment = Equipment::factory()->for($otherCentre)->create();
        $assignedRate = ResourceRate::factory()->for($assignedResource)->create();
        $otherRate = ResourceRate::factory()->for($otherResource)->create();
        $assignedEquipmentRate = EquipmentRate::factory()->for($assignedEquipment)->create();
        $otherEquipmentRate = EquipmentRate::factory()->for($otherEquipment)->create();

        $this->actingAs($manager);

        $this->assertTrue(Gate::allows('view', $assignedRate));
        $this->assertFalse(Gate::allows('view', $otherRate));
        $this->assertTrue(Gate::allows('view', $assignedEquipmentRate));
        $this->assertFalse(Gate::allows('view', $otherEquipmentRate));
        $this->assertSame([$assignedRate->id], ResourceRateResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame([$assignedEquipmentRate->id], EquipmentRateResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_manager_can_create_resource_and_equipment_rates_for_an_assigned_centre(): void
    {
        $manager = $this->manager();
        $centre = Centre::factory()->create();
        $manager->assignedCentres()->attach($centre);
        $resource = Resource::factory()->for(Facility::factory()->for($centre))->create();
        $equipment = Equipment::factory()->for($centre)->create();

        $this->actingAs($manager);

        Livewire::test(CreateResourceRate::class)
            ->fillForm([
                'resource_id' => $resource->id,
                'amount_minor' => 2500,
                'currency' => 'GBP',
                'rate_unit' => 'hour',
                'effective_from' => '2026-01-01',
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        Livewire::test(CreateEquipmentRate::class)
            ->fillForm([
                'equipment_id' => $equipment->id,
                'charge_type' => 'separately_chargeable',
                'amount_minor' => 500,
                'currency' => 'GBP',
                'rate_unit' => 'hour',
                'effective_from' => '2026-01-01',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('resource_rates', ['resource_id' => $resource->id, 'amount_minor' => 2500]);
        $this->assertDatabaseHas('equipment_rates', ['equipment_id' => $equipment->id, 'amount_minor' => 500]);
    }

    public function test_pricing_forms_reject_invalid_effective_dates_and_equipment_charge_amounts(): void
    {
        $manager = $this->manager();
        $centre = Centre::factory()->create();
        $manager->assignedCentres()->attach($centre);
        $resource = Resource::factory()->for(Facility::factory()->for($centre))->create();
        $equipment = Equipment::factory()->for($centre)->create();

        $this->actingAs($manager);

        Livewire::test(CreateResourceRate::class)
            ->fillForm([
                'resource_id' => $resource->id,
                'amount_minor' => 2500,
                'currency' => 'GBP',
                'rate_unit' => 'hour',
                'effective_from' => '2026-02-02',
                'effective_until' => '2026-02-01',
            ])
            ->call('create')
            ->assertHasFormErrors(['effective_until']);
        Livewire::test(CreateEquipmentRate::class)
            ->fillForm([
                'equipment_id' => $equipment->id,
                'charge_type' => 'included',
                'amount_minor' => 100,
                'currency' => 'GBP',
                'rate_unit' => 'hour',
                'effective_from' => '2026-01-01',
            ])
            ->call('create')
            ->assertHasFormErrors(['amount_minor']);
    }

    private function manager(): User
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        return $manager;
    }
}
