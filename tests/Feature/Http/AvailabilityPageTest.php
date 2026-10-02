<?php

namespace Tests\Feature\Http;

use App\Models\Centre;
use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Resource;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AvailabilityPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_named_route_accepts_guest_get_requests(): void
    {
        $route = Route::getRoutes()->getByName('availability.index');

        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame('availability', $route->uri());
    }

    public function test_guests_receive_active_public_venue_configuration_only(): void
    {
        $this->withoutVite();

        $activeCentre = Centre::factory()->create(['name' => 'Active Centre']);
        $activeFacility = Facility::factory()->for($activeCentre)->create(['name' => 'Active Facility']);
        $activeResource = Resource::factory()->for($activeFacility)->create(['name' => 'Active Resource']);
        $centreEquipment = Equipment::factory()->for($activeCentre)->create([
            'name' => 'Centre Equipment',
            'quantity' => 4,
        ]);
        $facilityEquipment = Equipment::factory()->forFacility($activeFacility)->create([
            'name' => 'Facility Equipment',
            'quantity' => 2,
        ]);
        $inactiveCentre = Centre::factory()->inactive()->create(['name' => 'Inactive Centre']);
        $inactiveFacility = Facility::factory()->inactive()->for($activeCentre)->create(['name' => 'Inactive Facility']);
        $inactiveResource = Resource::factory()->inactive()->for($activeFacility)->create(['name' => 'Inactive Resource']);
        $inactiveCentreFacility = Facility::factory()->for($inactiveCentre)->create(['name' => 'Inactive Centre Facility']);
        $inactiveCentreResource = Resource::factory()->for($inactiveCentreFacility)->create(['name' => 'Inactive Centre Resource']);
        $inactiveEquipment = Equipment::factory()->inactive()->for($activeCentre)->create(['name' => 'Inactive Equipment']);

        $this->get(route('availability.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('availability/Index')
                ->where('centres', [[
                    'id' => $activeCentre->id,
                    'name' => 'Active Centre',
                ]])
                ->where('facilities', [[
                    'id' => $activeFacility->id,
                    'centre_id' => $activeCentre->id,
                    'name' => 'Active Facility',
                ]])
                ->where('resources', [[
                    'id' => $activeResource->id,
                    'facility_id' => $activeFacility->id,
                    'name' => 'Active Resource',
                ]])
                ->where('equipment', [
                    [
                        'id' => $centreEquipment->id,
                        'centre_id' => $activeCentre->id,
                        'facility_id' => null,
                        'name' => 'Centre Equipment',
                        'quantity' => 4,
                    ],
                    [
                        'id' => $facilityEquipment->id,
                        'centre_id' => $activeCentre->id,
                        'facility_id' => $activeFacility->id,
                        'name' => 'Facility Equipment',
                        'quantity' => 2,
                    ],
                ])
                ->missing('centres.0.description')
                ->missing('facilities.0.description')
                ->missing('resources.0.setup_minutes')
                ->missing('resources.0.cleanup_minutes')
                ->missing('resources.0.allocation_units')
                ->missing('allocationUnits')
                ->missing('allocationOccupancies')
                ->missing('availabilityBlocks')
                ->missing('equipmentAllocations'),
            );

        $this->assertNotSame($activeCentre->id, $inactiveCentre->id);
        $this->assertNotSame($activeFacility->id, $inactiveFacility->id);
        $this->assertNotSame($activeResource->id, $inactiveResource->id);
        $this->assertNotSame($activeResource->id, $inactiveCentreResource->id);
        $this->assertNotSame($inactiveEquipment->id, $centreEquipment->id);
    }

    public function test_pricing_change_message_is_shared_when_review_returns_to_availability(): void
    {
        $this->withoutVite()
            ->withSession([
                'bookingReviewError' => 'Pricing changed before review. Please check availability again.',
            ])
            ->get(route('availability.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where(
                    'reviewError',
                    'Pricing changed before review. Please check availability again.',
                ),
            );
    }
}
