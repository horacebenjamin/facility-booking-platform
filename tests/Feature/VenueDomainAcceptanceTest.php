<?php

namespace Tests\Feature;

use App\Filament\Resources\Centres\CentreResource;
use App\Models\Centre;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\User;
use Database\Seeders\VenueDevelopmentSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VenueDomainAcceptanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_fictional_venue_dataset_supports_manager_configuration_of_a_divisible_facility(): void
    {
        $this->seed(VenueDevelopmentSeeder::class);

        $manager = User::query()->where('email', 'manager@facility4hire.test')->sole();
        $hillside = Centre::query()->where('slug', 'hillside-community-sports-centre')->sole();
        $sportsHall = Facility::query()
            ->whereBelongsTo($hillside)
            ->where('slug', 'sports-hall')
            ->sole();
        $wholeSportsHall = Resource::query()
            ->whereBelongsTo($sportsHall)
            ->where('slug', 'whole-sports-hall')
            ->sole();

        $this->actingAs($manager)
            ->get(CentreResource::getUrl())
            ->assertOk()
            ->assertSee('Hillside Community Sports Centre');

        $this->assertSame(
            ['A', 'B', 'C', 'D'],
            $wholeSportsHall->allocationUnits()->orderBy('allocation_units.code')->pluck('code')->all(),
        );

        $this->assertSame(
            ['A'],
            Resource::query()
                ->whereBelongsTo($sportsHall)
                ->where('slug', 'court-1')
                ->sole()
                ->allocationUnits()
                ->pluck('code')
                ->all(),
        );

        $this->assertSame(
            0,
            DB::table('allocation_unit_resource')
                ->join('resources', 'resources.id', '=', 'allocation_unit_resource.resource_id')
                ->join('allocation_units', 'allocation_units.id', '=', 'allocation_unit_resource.allocation_unit_id')
                ->whereColumn('allocation_unit_resource.facility_id', '!=', 'resources.facility_id')
                ->orWhereColumn('allocation_unit_resource.facility_id', '!=', 'allocation_units.facility_id')
                ->count(),
        );

        $this->assertDatabaseHas('equipment', [
            'centre_id' => $hillside->id,
            'facility_id' => null,
            'name' => 'Folding tables',
            'quantity' => 12,
        ]);

        $this->assertDatabaseHas('equipment', [
            'centre_id' => $hillside->id,
            'facility_id' => $sportsHall->id,
            'name' => 'Badminton nets',
            'quantity' => 4,
        ]);

        $this->assertSame(7, $hillside->operatingHours()->count());
        $this->assertSame(7, $sportsHall->bookableHours()->count());
        $this->assertSame(7, $wholeSportsHall->bookableHours()->count());
        $this->assertSame(15, $wholeSportsHall->setup_minutes);
        $this->assertSame(15, $wholeSportsHall->cleanup_minutes);

        $this->assertSame(['manager'], $manager->roles()->pluck('name')->all());
        $this->assertSame(
            ['hillside-community-sports-centre', 'riverside-activity-centre'],
            $manager->assignedCentres()->orderBy('centres.slug')->pluck('slug')->all(),
        );
    }
}
