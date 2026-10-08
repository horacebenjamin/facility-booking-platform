<?php

namespace Tests\Feature;

use App\Actions\UpdateEquipment;
use App\Filament\Resources\Centres\Pages\EditCentre;
use App\Filament\Resources\Centres\RelationManagers\EquipmentRelationManager;
use App\Filament\Resources\Equipment\Pages\EditEquipment;
use App\Filament\Resources\Resources\Pages\EditResource;
use App\Filament\Resources\Resources\RelationManagers\AllocationUnitsRelationManager;
use App\Models\AllocationOccupancy;
use App\Models\AllocationUnit;
use App\Models\Booking;
use App\Models\Centre;
use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\User;
use Database\Seeders\SystemRoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ReservationConfigurationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemRoleSeeder::class);
        $this->travelTo('2026-10-01 12:00:00');
    }

    public function test_equipment_capacity_cannot_be_reduced_below_peak_active_usage_and_the_whole_edit_rolls_back(): void
    {
        $equipment = Equipment::factory()->create(['quantity' => 5, 'name' => 'Original']);
        $this->allocation($equipment, '18:00', '20:00', 2);
        $this->allocation($equipment, '19:00', '21:00', 3);
        try {
            app(UpdateEquipment::class)->handle($this->manager($equipment->centre), $equipment, ['quantity' => 4, 'name' => 'Unsafe edit']);
            $this->fail('Active simultaneous usage must prevent this decrease.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
        $this->assertSame(5, $equipment->fresh()->quantity);
        $this->assertSame('Original', $equipment->fresh()->name);
    }

    public function test_capacity_uses_peak_usage_with_half_open_boundaries_and_ignores_expired_and_past_allocations(): void
    {
        $equipment = Equipment::factory()->create(['quantity' => 8]);
        $this->allocation($equipment, '18:00', '19:00', 3);
        $this->allocation($equipment, '19:00', '20:00', 3);
        $this->allocation($equipment, '18:00', '20:00', 8, ['expires_at' => now()]);
        EquipmentAllocation::factory()->for($equipment)->create(['quantity' => 8, 'starts_at' => now()->subHours(2), 'ends_at' => now(), 'expires_at' => null]);
        app(UpdateEquipment::class)->handle($this->manager($equipment->centre), $equipment, ['quantity' => 3]);
        $this->assertSame(3, $equipment->fresh()->quantity);
        $this->assertDatabaseCount('equipment_allocations', 4);
    }

    public function test_active_equipment_cannot_move_even_when_the_manager_is_assigned_to_both_centres(): void
    {
        $equipment = Equipment::factory()->create();
        $destination = Centre::factory()->create();
        $manager = $this->manager($equipment->centre);
        $manager->assignedCentres()->attach($destination);
        $this->allocation($equipment, '18:00', '19:00', 1);
        try {
            app(UpdateEquipment::class)->handle($manager, $equipment, ['centre_id' => $destination->id]);
            $this->fail('Active equipment must retain its location.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('facility_id', $exception->errors());
        }
        $this->assertSame($equipment->centre_id, $equipment->fresh()->centre_id);
    }

    public function test_equipment_can_move_after_protection_expires_but_requires_both_centre_assignments(): void
    {
        $equipment = Equipment::factory()->create();
        $destination = Facility::factory()->create();
        $manager = $this->manager($equipment->centre);
        $this->allocation($equipment, '18:00', '19:00', 1, ['expires_at' => now()]);
        try {
            app(UpdateEquipment::class)->handle($manager, $equipment, ['centre_id' => $destination->centre_id, 'facility_id' => $destination->id]);
            $this->fail('Destination authorization is required.');
        } catch (AuthorizationException) {
        }
        $manager->assignedCentres()->attach($destination->centre_id);
        app(UpdateEquipment::class)->handle($manager, $equipment, ['centre_id' => $destination->centre_id, 'facility_id' => $destination->id]);
        $this->assertSame($destination->id, $equipment->fresh()->facility_id);
    }

    public function test_operations_role_cannot_edit_inventory_with_a_manager_capability(): void
    {
        $equipment = Equipment::factory()->create();
        $actor = User::factory()->create();
        $actor->assignRole('leisure-assistant');
        $actor->givePermissionTo('facilities.manage');
        $actor->assignedCentres()->attach($equipment->centre_id);
        $this->expectException(AuthorizationException::class);
        app(UpdateEquipment::class)->handle($actor, $equipment, ['quantity' => 0]);
    }

    public function test_resource_remapping_is_blocked_while_protected_then_allowed_after_expiry(): void
    {
        [$resource, $unit, $occupancy] = $this->protectedResource();
        $replacement = AllocationUnit::factory()->for($resource->facility)->create();
        try {
            $resource->syncAllocationUnits($replacement);
            $this->fail('Remapping must preserve active booking protection.');
        } catch (ValidationException) {
        }
        $this->assertSame([$unit->id], $resource->allocationUnits()->pluck('allocation_units.id')->all());
        $resource->syncAllocationUnits($unit);
        $occupancy->update(['expires_at' => now()]);
        $resource->syncAllocationUnits($replacement);
        $this->assertSame([$replacement->id], $resource->allocationUnits()->pluck('allocation_units.id')->all());
        $this->assertSame([$unit->id], $occupancy->allocationUnits()->pluck('allocation_units.id')->all());
    }

    public function test_both_equipment_edit_interfaces_enforce_inventory_protection(): void
    {
        $equipment = Equipment::factory()->create(['quantity' => 3]);
        $this->allocation($equipment, '18:00', '19:00', 3);
        $this->actingAs($this->manager($equipment->centre));
        Livewire::test(EditEquipment::class, ['record' => $equipment->getRouteKey()])
            ->fillForm(['quantity' => 2])->call('save')->assertHasFormErrors(['quantity']);
        Livewire::test(EquipmentRelationManager::class, ['ownerRecord' => $equipment->centre, 'pageClass' => EditCentre::class])
            ->callAction(TestAction::make('edit')->table($equipment), ['name' => $equipment->name, 'quantity' => 2, 'is_active' => true])
            ->assertNotified('Equipment could not be updated');
        $this->assertSame(3, $equipment->fresh()->quantity);
    }

    public function test_allocation_relation_actions_cannot_detach_or_attach_during_active_protection(): void
    {
        [$resource, $unit] = $this->protectedResource();
        $replacement = AllocationUnit::factory()->for($resource->facility)->create();
        $this->actingAs($this->manager($resource->facility->centre));
        $component = Livewire::test(AllocationUnitsRelationManager::class, ['ownerRecord' => $resource, 'pageClass' => EditResource::class]);
        $component->callAction(TestAction::make('detach')->table($unit))->assertNotified('Allocation units could not be changed');
        Livewire::test(AllocationUnitsRelationManager::class, ['ownerRecord' => $resource, 'pageClass' => EditResource::class])
            ->callAction(TestAction::make('attach')->table(), ['recordId' => $replacement->id, 'facility_id' => $resource->facility_id])
            ->assertNotified('Allocation units could not be changed');
        $this->assertSame([$unit->id], $resource->allocationUnits()->pluck('allocation_units.id')->all());
    }

    /** @param array<string, mixed> $attributes */
    private function allocation(Equipment $equipment, string $start, string $end, int $quantity, array $attributes = []): void
    {
        EquipmentAllocation::factory()->for($equipment)->create([
            'starts_at' => '2026-10-05 '.$start, 'ends_at' => '2026-10-05 '.$end,
            'quantity' => $quantity, 'expires_at' => null, ...$attributes,
        ]);
    }

    /** @return array{resource, AllocationUnit, AllocationOccupancy} */
    private function protectedResource(): array
    {
        $resource = Resource::factory()->create();
        $unit = AllocationUnit::factory()->for($resource->facility)->create();
        $resource->syncAllocationUnits($unit);
        $booking = Booking::factory()->for($resource)->create(['starts_at' => '2026-10-05 18:00', 'ends_at' => '2026-10-05 19:00']);
        $occupancy = AllocationOccupancy::factory()->for($booking)->create(['starts_at' => $booking->starts_at, 'ends_at' => $booking->ends_at, 'expires_at' => null]);
        $occupancy->allocationUnits()->attach($unit);

        return [$resource, $unit, $occupancy];
    }

    private function manager(Centre $centre): User
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->assignedCentres()->attach($centre);

        return $manager;
    }
}
