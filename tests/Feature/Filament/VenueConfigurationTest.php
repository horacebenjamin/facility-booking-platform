<?php

namespace Tests\Feature\Filament;

use App\Enums\DayOfWeek;
use App\Filament\Resources\Centres\CentreResource;
use App\Filament\Resources\Centres\Pages\CreateCentre;
use App\Filament\Resources\Centres\Pages\EditCentre;
use App\Filament\Resources\Centres\RelationManagers\AssignedUsersRelationManager;
use App\Filament\Resources\Centres\RelationManagers\EquipmentRelationManager;
use App\Filament\Resources\Centres\RelationManagers\OperatingHoursRelationManager as CentreOperatingHoursRelationManager;
use App\Filament\Resources\Equipment\Pages\CreateEquipment;
use App\Filament\Resources\Facilities\Pages\CreateFacility;
use App\Filament\Resources\Facilities\Pages\EditFacility;
use App\Filament\Resources\Facilities\RelationManagers\AllocationUnitsRelationManager as FacilityAllocationUnitsRelationManager;
use App\Filament\Resources\Facilities\RelationManagers\BookableHoursRelationManager as FacilityBookableHoursRelationManager;
use App\Filament\Resources\Resources\Pages\CreateResource;
use App\Filament\Resources\Resources\Pages\EditResource;
use App\Filament\Resources\Resources\RelationManagers\AllocationUnitsRelationManager;
use App\Filament\Resources\Resources\RelationManagers\BookableHoursRelationManager as ResourceBookableHoursRelationManager;
use App\Models\AllocationUnit;
use App\Models\Centre;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\User;
use Database\Seeders\SystemRoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VenueConfigurationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemRoleSeeder::class);
    }

    public function test_manager_can_access_centre_management_and_other_staff_cannot(): void
    {
        $manager = $this->manager();
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        $leisureAssistant = User::factory()->create();
        $leisureAssistant->assignRole('leisure-assistant');

        $this->actingAs($manager)->get(CentreResource::getUrl())->assertOk();
        $this->actingAs($customer)->get(CentreResource::getUrl())->assertForbidden();
        $this->actingAs($leisureAssistant)->get(CentreResource::getUrl())->assertForbidden();
    }

    public function test_manager_cannot_read_or_change_an_unassigned_centres_configuration(): void
    {
        $local = Centre::factory()->create(['name' => 'Local Centre']);
        $foreign = Centre::factory()->create(['name' => 'Foreign Centre']);
        $manager = $this->manager();
        $manager->assignedCentres()->attach($local);
        $this->actingAs($manager);

        $this->get(CentreResource::getUrl())->assertOk()->assertSee('Local Centre')->assertDontSee('Foreign Centre');
        $this->get(CentreResource::getUrl('edit', ['record' => $foreign]))->assertNotFound();
        Livewire::test(CreateFacility::class)
            ->fillForm(['centre_id' => $foreign->id, 'name' => 'Foreign Hall', 'slug' => 'foreign-hall'])
            ->call('create')
            ->assertHasFormErrors(['centre_id']);

        $this->assertDatabaseMissing('facilities', ['centre_id' => $foreign->id, 'name' => 'Foreign Hall']);
    }

    public function test_losing_centre_assignment_blocks_an_already_open_edit_form(): void
    {
        $centre = Centre::factory()->create(['name' => 'Assigned Centre']);
        $manager = $this->manager();
        $manager->assignedCentres()->attach($centre);
        $this->actingAs($manager);
        $form = Livewire::test(EditCentre::class, ['record' => $centre->getRouteKey()])
            ->fillForm(['name' => 'Unauthorized update']);

        $manager->assignedCentres()->detach($centre);

        $form->call('save')->assertForbidden();
        $this->assertSame('Assigned Centre', $centre->fresh()->name);
    }

    public function test_losing_centre_assignment_blocks_an_already_open_relation_action(): void
    {
        $centre = Centre::factory()->create();
        $manager = $this->manager();
        $manager->assignedCentres()->attach($centre);
        $this->actingAs($manager);
        $hours = Livewire::test(CentreOperatingHoursRelationManager::class, [
            'ownerRecord' => $centre,
            'pageClass' => EditCentre::class,
        ]);

        $manager->assignedCentres()->detach($centre);

        $hours->call('mountTableAction', 'create')->assertForbidden();
        $this->assertDatabaseCount('centre_operating_hours', 0);
    }

    public function test_manager_can_create_and_update_a_centre(): void
    {
        $this->actingAs($this->manager());

        Livewire::test(CreateCentre::class)
            ->fillForm($this->centreData())
            ->call('create')
            ->assertHasNoFormErrors();

        $centre = Centre::query()->sole();

        Livewire::test(EditCentre::class, ['record' => $centre->getRouteKey()])
            ->fillForm(['name' => 'Updated Centre'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Updated Centre', $centre->fresh()->name);
    }

    public function test_manager_can_configure_facilities_resources_equipment_and_active_state(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager);
        $centre = Centre::factory()->create();
        $manager->assignedCentres()->attach($centre);

        Livewire::test(CreateFacility::class)
            ->fillForm([
                'centre_id' => $centre->id,
                'name' => 'Sports Hall',
                'slug' => 'sports-hall',
                'capacity' => 100,
                'is_active' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $facility = Facility::query()->sole();

        Livewire::test(FacilityAllocationUnitsRelationManager::class, [
            'ownerRecord' => $facility,
            'pageClass' => EditFacility::class,
        ])->callAction(TestAction::make('create')->table(), [
            'name' => 'Court A',
            'code' => 'A',
            'is_active' => true,
        ])->assertHasNoActionErrors();

        Livewire::test(CreateResource::class)
            ->fillForm([
                'facility_id' => $facility->id,
                'name' => 'Whole Hall',
                'slug' => 'whole-hall',
                'setup_minutes' => 15,
                'cleanup_minutes' => 10,
                'is_active' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CreateEquipment::class)
            ->fillForm([
                'centre_id' => $centre->id,
                'name' => 'Chairs',
                'quantity' => 0,
                'is_active' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        Livewire::test(CreateEquipment::class)
            ->fillForm([
                'centre_id' => $centre->id,
                'facility_id' => $facility->id,
                'name' => 'Scoreboard',
                'quantity' => 1,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('resources', [
            'facility_id' => $facility->id,
            'setup_minutes' => 15,
            'cleanup_minutes' => 10,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('allocation_units', ['facility_id' => $facility->id, 'code' => 'A']);
        $this->assertDatabaseHas('equipment', ['centre_id' => $centre->id, 'facility_id' => null, 'quantity' => 0, 'is_active' => false]);
        $this->assertDatabaseHas('equipment', ['centre_id' => $centre->id, 'facility_id' => $facility->id, 'name' => 'Scoreboard']);
    }

    public function test_equipment_form_rejects_invalid_centre_facility_and_negative_quantity(): void
    {
        $this->actingAs($this->manager());
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->create();

        Livewire::test(CreateEquipment::class)
            ->fillForm([
                'centre_id' => $centre->id,
                'facility_id' => $facility->id,
                'name' => 'Invalid equipment',
                'quantity' => -1,
            ])
            ->call('create')
            ->assertHasFormErrors(['facility_id', 'quantity']);

        $this->assertDatabaseMissing('equipment', ['name' => 'Invalid equipment']);
    }

    public function test_centre_equipment_action_rejects_a_facility_from_another_centre(): void
    {
        $manager = $this->manager();
        $centre = Centre::factory()->create();
        $foreignFacility = Facility::factory()->create();
        $manager->assignedCentres()->attach($centre);
        $this->actingAs($manager);

        Livewire::test(EquipmentRelationManager::class, [
            'ownerRecord' => $centre,
            'pageClass' => EditCentre::class,
        ])->callAction(TestAction::make('create')->table(), [
            'facility_id' => $foreignFacility->id,
            'name' => 'Forged equipment',
            'quantity' => 1,
        ])->assertHasActionErrors(['facility_id']);

        $this->assertDatabaseMissing('equipment', ['name' => 'Forged equipment']);
    }

    public function test_manager_can_attach_a_resource_to_an_allocation_unit_from_its_facility_only(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager);
        $facility = Facility::factory()->create();
        $manager->assignedCentres()->attach($facility->centre_id);
        $resource = Resource::factory()->for($facility)->create();
        $validAllocationUnit = AllocationUnit::factory()->for($facility)->create();
        $invalidAllocationUnit = AllocationUnit::factory()->create();

        $component = Livewire::test(AllocationUnitsRelationManager::class, [
            'ownerRecord' => $resource,
            'pageClass' => EditResource::class,
        ])->assertCanSeeTableRecords([]);

        $component->callAction(TestAction::make('attach')->table(), [
            'recordId' => $validAllocationUnit->id,
            'facility_id' => $facility->id,
        ]);

        $this->assertDatabaseHas('allocation_unit_resource', [
            'resource_id' => $resource->id,
            'allocation_unit_id' => $validAllocationUnit->id,
            'facility_id' => $facility->id,
        ]);
        $this->assertDatabaseMissing('allocation_unit_resource', [
            'resource_id' => $resource->id,
            'allocation_unit_id' => $invalidAllocationUnit->id,
        ]);
    }

    public function test_manager_can_configure_each_kind_of_weekly_hours_and_invalid_ranges_fail_validation(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager);
        $centre = Centre::factory()->create();
        $manager->assignedCentres()->attach($centre);
        $facility = Facility::factory()->for($centre)->create();
        $resource = Resource::factory()->for($facility)->create();

        Livewire::test(CentreOperatingHoursRelationManager::class, ['ownerRecord' => $centre, 'pageClass' => EditCentre::class])
            ->callAction(TestAction::make('create')->table(), $this->hoursData())
            ->assertHasNoActionErrors();
        Livewire::test(FacilityBookableHoursRelationManager::class, ['ownerRecord' => $facility, 'pageClass' => EditFacility::class])
            ->callAction(TestAction::make('create')->table(), $this->hoursData())
            ->assertHasNoActionErrors();
        Livewire::test(ResourceBookableHoursRelationManager::class, ['ownerRecord' => $resource, 'pageClass' => EditResource::class])
            ->callAction(TestAction::make('create')->table(), $this->hoursData())
            ->assertHasNoActionErrors();

        Livewire::test(CentreOperatingHoursRelationManager::class, ['ownerRecord' => $centre, 'pageClass' => EditCentre::class])
            ->callAction(TestAction::make('create')->table(), ['day_of_week' => DayOfWeek::Tuesday->value, 'opens_at' => '18:00', 'closes_at' => '18:00'])
            ->assertHasActionErrors(['closes_at']);
        Livewire::test(CentreOperatingHoursRelationManager::class, ['ownerRecord' => $centre, 'pageClass' => EditCentre::class])
            ->callAction(TestAction::make('create')->table(), ['day_of_week' => DayOfWeek::Wednesday->value, 'opens_at' => '18:00', 'closes_at' => '08:00'])
            ->assertHasActionErrors(['closes_at']);
        Livewire::test(CentreOperatingHoursRelationManager::class, ['ownerRecord' => $centre, 'pageClass' => EditCentre::class])
            ->callAction(TestAction::make('create')->table(), $this->hoursData())
            ->assertHasActionErrors(['day_of_week']);

        $this->assertDatabaseHas('centre_operating_hours', ['centre_id' => $centre->id, 'day_of_week' => DayOfWeek::Monday->value]);
        $this->assertDatabaseHas('facility_bookable_hours', ['facility_id' => $facility->id, 'day_of_week' => DayOfWeek::Monday->value]);
        $this->assertDatabaseHas('resource_bookable_hours', ['resource_id' => $resource->id, 'day_of_week' => DayOfWeek::Monday->value]);
    }

    public function test_resource_form_rejects_negative_setup_and_cleanup_minutes(): void
    {
        $this->actingAs($this->manager());
        $facility = Facility::factory()->create();

        Livewire::test(CreateResource::class)
            ->fillForm([
                'facility_id' => $facility->id,
                'name' => 'Invalid timings',
                'slug' => 'invalid-timings',
                'setup_minutes' => -1,
                'cleanup_minutes' => -1,
            ])
            ->call('create')
            ->assertHasFormErrors(['setup_minutes', 'cleanup_minutes']);
    }

    public function test_manager_can_assign_staff_without_changing_roles(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager);
        $centre = Centre::factory()->create();
        $manager->assignedCentres()->attach($centre);
        $staffMember = User::factory()->create();
        $staffMember->assignRole('leisure-assistant');
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        Livewire::test(AssignedUsersRelationManager::class, ['ownerRecord' => $centre, 'pageClass' => EditCentre::class])
            ->callAction(TestAction::make('attach')->table(), ['recordId' => $staffMember->id]);

        $this->assertTrue($centre->assignedUsers()->whereKey($staffMember)->exists());
        $this->assertFalse($centre->assignedUsers()->whereKey($customer)->exists());
        $this->assertTrue($staffMember->hasRole('leisure-assistant'));
        $this->assertFalse($staffMember->hasRole('manager'));
    }

    /** @return array<string, mixed> */
    private function centreData(): array
    {
        return [
            'name' => 'Central Leisure Centre',
            'slug' => 'central-leisure-centre',
            'address_line_1' => '1 High Street',
            'locality' => 'London',
            'postcode' => 'SW1A 1AA',
            'is_active' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function hoursData(): array
    {
        return ['day_of_week' => DayOfWeek::Monday->value, 'opens_at' => '08:00', 'closes_at' => '22:00'];
    }

    private function manager(): User
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        return $manager;
    }
}
