<?php

namespace Database\Seeders;

use App\Enums\DayOfWeek;
use App\Models\AllocationUnit;
use App\Models\Centre;
use App\Models\CentreOperatingHour;
use App\Models\Equipment;
use App\Models\EquipmentRate;
use App\Models\Facility;
use App\Models\FacilityBookableHour;
use App\Models\Resource;
use App\Models\ResourceBookableHour;
use App\Models\ResourceRate;
use App\Models\User;
use Illuminate\Database\Seeder;

class VenueDevelopmentSeeder extends Seeder
{
    /**
     * @var list<array{day_of_week: DayOfWeek, opens_at: string, closes_at: string}>
     */
    private const CentreOperatingHours = [
        ['day_of_week' => DayOfWeek::Monday, 'opens_at' => '07:00', 'closes_at' => '22:00'],
        ['day_of_week' => DayOfWeek::Tuesday, 'opens_at' => '07:00', 'closes_at' => '22:00'],
        ['day_of_week' => DayOfWeek::Wednesday, 'opens_at' => '07:00', 'closes_at' => '22:00'],
        ['day_of_week' => DayOfWeek::Thursday, 'opens_at' => '07:00', 'closes_at' => '22:00'],
        ['day_of_week' => DayOfWeek::Friday, 'opens_at' => '07:00', 'closes_at' => '22:00'],
        ['day_of_week' => DayOfWeek::Saturday, 'opens_at' => '08:00', 'closes_at' => '20:00'],
        ['day_of_week' => DayOfWeek::Sunday, 'opens_at' => '09:00', 'closes_at' => '18:00'],
    ];

    /**
     * @var list<array{day_of_week: DayOfWeek, opens_at: string, closes_at: string}>
     */
    private const WeekdayAndWeekendBookableHours = [
        ['day_of_week' => DayOfWeek::Monday, 'opens_at' => '08:00', 'closes_at' => '21:00'],
        ['day_of_week' => DayOfWeek::Tuesday, 'opens_at' => '08:00', 'closes_at' => '21:00'],
        ['day_of_week' => DayOfWeek::Wednesday, 'opens_at' => '08:00', 'closes_at' => '21:00'],
        ['day_of_week' => DayOfWeek::Thursday, 'opens_at' => '08:00', 'closes_at' => '21:00'],
        ['day_of_week' => DayOfWeek::Friday, 'opens_at' => '08:00', 'closes_at' => '21:00'],
        ['day_of_week' => DayOfWeek::Saturday, 'opens_at' => '09:00', 'closes_at' => '18:00'],
        ['day_of_week' => DayOfWeek::Sunday, 'opens_at' => '09:00', 'closes_at' => '17:00'],
    ];

    /**
     * Seed a small, explicitly fictional Facility4Hire development dataset.
     */
    public function run(): void
    {
        $this->call(SystemRoleSeeder::class);

        $hillside = Centre::query()->updateOrCreate(
            ['slug' => 'hillside-community-sports-centre'],
            [
                'name' => 'Hillside Community Sports Centre',
                'description' => 'Fictional Facility4Hire development venue in Sheffield.',
                'address_line_1' => '14 Fictional Lane',
                'address_line_2' => null,
                'locality' => 'Sheffield',
                'postcode' => 'S0 1AA',
                'is_active' => true,
            ],
        );

        $riverside = Centre::query()->updateOrCreate(
            ['slug' => 'riverside-activity-centre'],
            [
                'name' => 'Riverside Activity Centre',
                'description' => 'Fictional Facility4Hire development venue in Sheffield.',
                'address_line_1' => '8 Imaginary Quay',
                'address_line_2' => null,
                'locality' => 'Sheffield',
                'postcode' => 'S0 2BB',
                'is_active' => true,
            ],
        );

        $this->seedCentreOperatingHours($hillside);
        $this->seedCentreOperatingHours($riverside);

        $sportsHall = $this->seedFacility($hillside, 'sports-hall', 'Sports Hall', 120, 'Divisible indoor sports hall for court and whole-hall bookings.');
        $studio = $this->seedFacility($hillside, 'activity-studio', 'Activity Studio', 30, 'Flexible studio for classes and community activities.');
        $astroPitch = $this->seedFacility($riverside, 'astro-pitch', 'Astro Pitch', 40, 'Outdoor all-weather pitch divisible into two half pitches.');
        $meetingRoom = $this->seedFacility($riverside, 'meeting-room', 'Meeting Room', 20, 'Small meeting room for local groups and training.');

        $this->seedFacilityBookableHours($sportsHall, self::WeekdayAndWeekendBookableHours);
        $this->seedFacilityBookableHours($studio, self::WeekdayAndWeekendBookableHours);
        $this->seedFacilityBookableHours($astroPitch, self::WeekdayAndWeekendBookableHours);
        $this->seedFacilityBookableHours($meetingRoom, self::WeekdayAndWeekendBookableHours);

        $hallSectionA = $this->seedAllocationUnit($sportsHall, 'A', 'Hall Section A');
        $hallSectionB = $this->seedAllocationUnit($sportsHall, 'B', 'Hall Section B');
        $hallSectionC = $this->seedAllocationUnit($sportsHall, 'C', 'Hall Section C');
        $hallSectionD = $this->seedAllocationUnit($sportsHall, 'D', 'Hall Section D');
        $studioSpace = $this->seedAllocationUnit($studio, 'STUDIO', 'Studio Space');
        $pitchA = $this->seedAllocationUnit($astroPitch, 'A', 'Pitch A');
        $pitchB = $this->seedAllocationUnit($astroPitch, 'B', 'Pitch B');
        $meetingRoomSpace = $this->seedAllocationUnit($meetingRoom, 'ROOM', 'Meeting Room Space');

        $wholeSportsHall = $this->seedResource($sportsHall, 'whole-sports-hall', 'Whole Sports Hall', 120, 15, 15, $hallSectionA, $hallSectionB, $hallSectionC, $hallSectionD);
        $courtOne = $this->seedResource($sportsHall, 'court-1', 'Court 1', 30, 0, 0, $hallSectionA);
        $courtTwo = $this->seedResource($sportsHall, 'court-2', 'Court 2', 30, 0, 0, $hallSectionB);
        $courtThree = $this->seedResource($sportsHall, 'court-3', 'Court 3', 30, 0, 0, $hallSectionC);
        $courtFour = $this->seedResource($sportsHall, 'court-4', 'Court 4', 30, 0, 0, $hallSectionD);
        $activityStudio = $this->seedResource($studio, 'activity-studio', 'Activity Studio', 30, 5, 10, $studioSpace);
        $fullAstroPitch = $this->seedResource($astroPitch, 'full-astro-pitch', 'Full Astro Pitch', 40, 10, 10, $pitchA, $pitchB);
        $halfPitchA = $this->seedResource($astroPitch, 'half-pitch-a', 'Half Pitch A', 20, 0, 5, $pitchA);
        $halfPitchB = $this->seedResource($astroPitch, 'half-pitch-b', 'Half Pitch B', 20, 0, 5, $pitchB);
        $meetingRoomResource = $this->seedResource($meetingRoom, 'meeting-room', 'Meeting Room', 20, 5, 5, $meetingRoomSpace);

        $this->seedResourceRate($wholeSportsHall, 7200);
        $this->seedResourceRate($courtOne, 1850);
        $this->seedResourceRate($courtTwo, 1850);
        $this->seedResourceRate($courtThree, 1850);
        $this->seedResourceRate($courtFour, 1850);
        $this->seedResourceRate($activityStudio, 3200);
        $this->seedResourceRate($fullAstroPitch, 6800);
        $this->seedResourceRate($halfPitchA, 3800);
        $this->seedResourceRate($halfPitchB, 3800);
        $this->seedResourceRate($meetingRoomResource, 2400);

        $foldingTables = $this->seedEquipment($hillside, null, 'Folding tables', 12, true, 'Centre-level tables for community activities.');
        $portablePaSystem = $this->seedEquipment($hillside, null, 'Portable PA system', 1, true, 'Centre-level sound system for events and classes.');
        $badmintonNets = $this->seedEquipment($hillside, $sportsHall, 'Badminton nets', 4, true, 'Sports Hall inventory.');
        $basketballHoops = $this->seedEquipment($hillside, $sportsHall, 'Basketball hoops', 2, true, 'Sports Hall inventory.');
        $trainingCones = $this->seedEquipment($riverside, null, 'Training cones', 40, true, 'Centre-level coaching equipment.');
        $foldingChairs = $this->seedEquipment($riverside, null, 'Folding chairs', 30, true, 'Centre-level seating for activities and meetings.');
        $footballGoals = $this->seedEquipment($riverside, $astroPitch, 'Football goals', 4, true, 'Astro Pitch inventory.');
        $portableProjector = $this->seedEquipment($riverside, $meetingRoom, 'Portable projector', 1, false, 'Fictional inactive item awaiting maintenance.');

        $this->seedEquipmentRate($foldingTables, 'separately_chargeable', 200);
        $this->seedEquipmentRate($portablePaSystem, 'separately_chargeable', 1500);
        $this->seedEquipmentRate($badmintonNets, 'included', 0);
        $this->seedEquipmentRate($basketballHoops, 'included', 0);
        $this->seedEquipmentRate($trainingCones, 'included', 0);
        $this->seedEquipmentRate($foldingChairs, 'separately_chargeable', 100);
        $this->seedEquipmentRate($footballGoals, 'included', 0);
        $this->seedEquipmentRate($portableProjector, 'separately_chargeable', 800);

        $this->seedStaff($hillside, $riverside);
    }

    private function seedCentreOperatingHours(Centre $centre): void
    {
        foreach (self::CentreOperatingHours as $hour) {
            CentreOperatingHour::query()->updateOrCreate(
                ['centre_id' => $centre->id, 'day_of_week' => $hour['day_of_week']],
                ['opens_at' => $hour['opens_at'], 'closes_at' => $hour['closes_at']],
            );
        }
    }

    /**
     * @param  list<array{day_of_week: DayOfWeek, opens_at: string, closes_at: string}>  $hours
     */
    private function seedFacilityBookableHours(Facility $facility, array $hours): void
    {
        foreach ($hours as $hour) {
            FacilityBookableHour::query()->updateOrCreate(
                ['facility_id' => $facility->id, 'day_of_week' => $hour['day_of_week']],
                ['opens_at' => $hour['opens_at'], 'closes_at' => $hour['closes_at']],
            );
        }
    }

    private function seedFacility(Centre $centre, string $slug, string $name, int $capacity, string $description): Facility
    {
        return Facility::query()->updateOrCreate(
            ['centre_id' => $centre->id, 'slug' => $slug],
            ['name' => $name, 'description' => $description, 'capacity' => $capacity, 'is_active' => true],
        );
    }

    private function seedAllocationUnit(Facility $facility, string $code, string $name): AllocationUnit
    {
        return AllocationUnit::query()->updateOrCreate(
            ['facility_id' => $facility->id, 'code' => $code],
            ['name' => $name, 'is_active' => true],
        );
    }

    private function seedResource(Facility $facility, string $slug, string $name, int $capacity, int $setupMinutes, int $cleanupMinutes, AllocationUnit ...$allocationUnits): Resource
    {
        $resource = Resource::query()->updateOrCreate(
            ['facility_id' => $facility->id, 'slug' => $slug],
            [
                'name' => $name,
                'capacity' => $capacity,
                'setup_minutes' => $setupMinutes,
                'cleanup_minutes' => $cleanupMinutes,
                'is_active' => true,
            ],
        );

        $resource->syncAllocationUnits(...$allocationUnits);
        $this->seedResourceBookableHours($resource, self::WeekdayAndWeekendBookableHours);

        return $resource;
    }

    /**
     * @param  list<array{day_of_week: DayOfWeek, opens_at: string, closes_at: string}>  $hours
     */
    private function seedResourceBookableHours(Resource $resource, array $hours): void
    {
        foreach ($hours as $hour) {
            ResourceBookableHour::query()->updateOrCreate(
                ['resource_id' => $resource->id, 'day_of_week' => $hour['day_of_week']],
                ['opens_at' => $hour['opens_at'], 'closes_at' => $hour['closes_at']],
            );
        }
    }

    private function seedEquipment(Centre $centre, ?Facility $facility, string $name, int $quantity, bool $isActive, string $description): Equipment
    {
        return Equipment::query()->updateOrCreate(
            ['centre_id' => $centre->id, 'facility_id' => $facility?->id, 'name' => $name],
            ['description' => $description, 'quantity' => $quantity, 'is_active' => $isActive],
        );
    }

    private function seedResourceRate(Resource $resource, int $amountMinor): void
    {
        ResourceRate::query()->updateOrCreate(
            ['resource_id' => $resource->id, 'effective_from' => '2026-01-01'],
            [
                'amount_minor' => $amountMinor,
                'currency' => 'GBP',
                'rate_unit' => 'hour',
                'effective_until' => null,
            ],
        );
    }

    private function seedEquipmentRate(Equipment $equipment, string $chargeType, int $amountMinor): void
    {
        EquipmentRate::query()->updateOrCreate(
            ['equipment_id' => $equipment->id, 'effective_from' => '2026-01-01'],
            [
                'charge_type' => $chargeType,
                'amount_minor' => $amountMinor,
                'currency' => 'GBP',
                'rate_unit' => 'hour',
                'effective_until' => null,
            ],
        );
    }

    private function seedStaff(Centre $hillside, Centre $riverside): void
    {
        $manager = User::query()->updateOrCreate(
            ['email' => 'manager@facility4hire.test'],
            [
                'name' => 'Fictional Venue Manager',
                'email_verified_at' => '2026-01-01 09:00:00',
                'password' => 'password',
            ],
        );
        $manager->syncRoles(['manager']);
        $manager->assignedCentres()->syncWithoutDetaching([$hillside->id, $riverside->id]);

        $leisureAssistant = User::query()->updateOrCreate(
            ['email' => 'assistant@facility4hire.test'],
            [
                'name' => 'Fictional Leisure Assistant',
                'email_verified_at' => '2026-01-01 09:00:00',
                'password' => 'password',
            ],
        );
        $leisureAssistant->syncRoles(['leisure-assistant']);
        $leisureAssistant->assignedCentres()->syncWithoutDetaching([$riverside->id]);
    }
}
