<?php

namespace Tests\Feature\Database\Seeders;

use App\Enums\DayOfWeek;
use App\Models\Centre;
use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\User;
use Database\Seeders\VenueDevelopmentSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VenueDevelopmentSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeds_the_fictional_venue_dataset_with_its_physical_structure_and_configuration(): void
    {
        $this->seedVenueDevelopmentData();

        $this->assertSame([
            'Hillside Community Sports Centre',
            'Riverside Activity Centre',
        ], Centre::query()->orderBy('name')->pluck('name')->all());

        $hillside = Centre::query()->where('slug', 'hillside-community-sports-centre')->sole();
        $riverside = Centre::query()->where('slug', 'riverside-activity-centre')->sole();
        $sportsHall = Facility::query()->where('centre_id', $hillside->id)->where('slug', 'sports-hall')->sole();
        $wholeSportsHall = Resource::query()->where('facility_id', $sportsHall->id)->where('slug', 'whole-sports-hall')->sole();

        $this->assertSame(['A', 'B', 'C', 'D'], $wholeSportsHall->allocationUnits()->orderBy('allocation_units.code')->pluck('code')->all());
        $this->assertSame(['A'], Resource::query()->where('facility_id', $sportsHall->id)->where('slug', 'court-1')->sole()->allocationUnits()->pluck('code')->all());
        $this->assertSame(['B'], Resource::query()->where('facility_id', $sportsHall->id)->where('slug', 'court-2')->sole()->allocationUnits()->pluck('code')->all());
        $this->assertSame(['C'], Resource::query()->where('facility_id', $sportsHall->id)->where('slug', 'court-3')->sole()->allocationUnits()->pluck('code')->all());
        $this->assertSame(['D'], Resource::query()->where('facility_id', $sportsHall->id)->where('slug', 'court-4')->sole()->allocationUnits()->pluck('code')->all());
        $this->assertSame(0, DB::table('allocation_unit_resource')
            ->join('resources', 'resources.id', '=', 'allocation_unit_resource.resource_id')
            ->join('allocation_units', 'allocation_units.id', '=', 'allocation_unit_resource.allocation_unit_id')
            ->whereColumn('allocation_unit_resource.facility_id', '!=', 'resources.facility_id')
            ->orWhereColumn('allocation_unit_resource.facility_id', '!=', 'allocation_units.facility_id')
            ->count());

        $this->assertDatabaseHas('equipment', ['centre_id' => $hillside->id, 'facility_id' => $sportsHall->id, 'name' => 'Badminton nets', 'quantity' => 4, 'is_active' => true]);
        $this->assertDatabaseHas('equipment', ['centre_id' => $riverside->id, 'facility_id' => null, 'name' => 'Training cones', 'quantity' => 40, 'is_active' => true]);
        $this->assertDatabaseHas('equipment', ['centre_id' => $riverside->id, 'name' => 'Portable projector', 'quantity' => 1, 'is_active' => false]);
        $this->assertSame(8, Equipment::query()->count());

        $this->assertSame(7, $hillside->operatingHours()->count());
        $this->assertDatabaseHas('centre_operating_hours', ['centre_id' => $hillside->id, 'day_of_week' => DayOfWeek::Monday->value, 'opens_at' => '07:00:00', 'closes_at' => '22:00:00']);
        $this->assertSame(7, $sportsHall->bookableHours()->count());
        $this->assertSame(7, $wholeSportsHall->bookableHours()->count());
        $this->assertDatabaseHas('facility_bookable_hours', ['facility_id' => $sportsHall->id, 'day_of_week' => DayOfWeek::Saturday->value, 'opens_at' => '09:00:00', 'closes_at' => '18:00:00']);
        $this->assertDatabaseHas('resource_bookable_hours', ['resource_id' => $wholeSportsHall->id, 'day_of_week' => DayOfWeek::Sunday->value, 'opens_at' => '09:00:00', 'closes_at' => '17:00:00']);
        $this->assertSame(15, $wholeSportsHall->setup_minutes);
        $this->assertSame(15, $wholeSportsHall->cleanup_minutes);

        $manager = User::query()->where('email', 'manager@facility4hire.test')->sole();
        $leisureAssistant = User::query()->where('email', 'assistant@facility4hire.test')->sole();

        $this->assertTrue($manager->hasRole('manager'));
        $this->assertSame(['manager'], $manager->roles()->pluck('name')->all());
        $this->assertSame([$hillside->id, $riverside->id], $manager->assignedCentres()->orderBy('centres.id')->pluck('centres.id')->all());
        $this->assertTrue($leisureAssistant->hasRole('leisure-assistant'));
        $this->assertSame(['leisure-assistant'], $leisureAssistant->roles()->pluck('name')->all());
        $this->assertSame([$riverside->id], $leisureAssistant->assignedCentres()->pluck('centres.id')->all());
        $this->assertSame(
            ['assistant@facility4hire.test', 'manager@facility4hire.test'],
            $riverside->assignedUsers()->orderBy('email')->pluck('email')->all(),
        );
    }

    public function test_is_idempotent_for_stable_venue_records_and_relationships(): void
    {
        $this->seedVenueDevelopmentData();
        $this->seed(VenueDevelopmentSeeder::class);

        $this->assertDatabaseCount('centres', 2);
        $this->assertDatabaseCount('facilities', 4);
        $this->assertDatabaseCount('resources', 10);
        $this->assertDatabaseCount('allocation_units', 8);
        $this->assertDatabaseCount('allocation_unit_resource', 14);
        $this->assertDatabaseCount('equipment', 8);
        $this->assertDatabaseCount('centre_operating_hours', 14);
        $this->assertDatabaseCount('facility_bookable_hours', 28);
        $this->assertDatabaseCount('resource_bookable_hours', 70);
        $this->assertDatabaseCount('centre_user', 3);
    }

    private function seedVenueDevelopmentData(): void
    {
        $this->seed(VenueDevelopmentSeeder::class);
    }
}
