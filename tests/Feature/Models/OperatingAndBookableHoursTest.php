<?php

namespace Tests\Feature\Models;

use App\Enums\DayOfWeek;
use App\Models\Centre;
use App\Models\CentreOperatingHour;
use App\Models\Facility;
use App\Models\FacilityBookableHour;
use App\Models\Resource;
use App\Models\ResourceBookableHour;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OperatingAndBookableHoursTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_centre_has_its_operating_hours(): void
    {
        $centre = Centre::factory()->create();
        $operatingHour = CentreOperatingHour::factory()->for($centre)->create();

        $this->assertTrue($centre->operatingHours->contains($operatingHour));
    }

    public function test_operating_hour_belongs_to_its_centre(): void
    {
        $centre = Centre::factory()->create();
        $operatingHour = CentreOperatingHour::factory()->for($centre)->create();

        $this->assertTrue($operatingHour->centre->is($centre));
    }

    public function test_facility_has_its_bookable_hours(): void
    {
        $facility = Facility::factory()->create();
        $bookableHour = FacilityBookableHour::factory()->for($facility)->create();

        $this->assertTrue($facility->bookableHours->contains($bookableHour));
    }

    public function test_facility_bookable_hour_belongs_to_its_facility(): void
    {
        $facility = Facility::factory()->create();
        $bookableHour = FacilityBookableHour::factory()->for($facility)->create();

        $this->assertTrue($bookableHour->facility->is($facility));
    }

    public function test_resource_has_its_bookable_hours(): void
    {
        $resource = Resource::factory()->create();
        $bookableHour = ResourceBookableHour::factory()->for($resource)->create();

        $this->assertTrue($resource->bookableHours->contains($bookableHour));
        $this->assertTrue($bookableHour->resource->is($resource));
    }

    public function test_facility_bookable_hours_can_differ_by_weekday(): void
    {
        $facility = Facility::factory()->create();
        FacilityBookableHour::factory()->for($facility)->create([
            'day_of_week' => DayOfWeek::Monday,
            'opens_at' => '08:00:00',
            'closes_at' => '20:00:00',
        ]);
        FacilityBookableHour::factory()->for($facility)->create([
            'day_of_week' => DayOfWeek::Tuesday,
            'opens_at' => '09:00:00',
            'closes_at' => '18:00:00',
        ]);

        $hoursByDay = $facility->bookableHours()
            ->orderBy('day_of_week')
            ->get()
            ->mapWithKeys(fn (FacilityBookableHour $hour): array => [$hour->day_of_week->value => $hour->opens_at])
            ->all();

        $this->assertSame([
            DayOfWeek::Monday->value => '08:00:00',
            DayOfWeek::Tuesday->value => '09:00:00',
        ], $hoursByDay);
    }

    public function test_database_rejects_duplicate_centre_operating_hours_for_a_weekday(): void
    {
        $centre = Centre::factory()->create();
        CentreOperatingHour::factory()->for($centre)->create(['day_of_week' => DayOfWeek::Monday]);

        $this->expectException(QueryException::class);

        CentreOperatingHour::factory()->for($centre)->create(['day_of_week' => DayOfWeek::Monday]);
    }

    public function test_database_rejects_equal_operating_hour_start_and_end_times(): void
    {
        $this->expectException(QueryException::class);

        CentreOperatingHour::factory()->create([
            'opens_at' => '09:00:00',
            'closes_at' => '09:00:00',
        ]);
    }

    public function test_database_rejects_reversed_facility_bookable_hour_times(): void
    {
        $this->expectException(QueryException::class);

        FacilityBookableHour::factory()->create([
            'opens_at' => '18:00:00',
            'closes_at' => '09:00:00',
        ]);
    }

    public function test_database_rejects_an_invalid_resource_bookable_hour_range(): void
    {
        $this->expectException(QueryException::class);

        ResourceBookableHour::factory()->create([
            'opens_at' => '12:00:00',
            'closes_at' => '12:00:00',
        ]);
    }

    public function test_setup_and_cleanup_minutes_persist(): void
    {
        $resource = Resource::factory()->create([
            'setup_minutes' => 15,
            'cleanup_minutes' => 30,
        ]);

        $this->assertSame(15, $resource->fresh()->setup_minutes);
        $this->assertSame(30, $resource->fresh()->cleanup_minutes);
    }

    public function test_zero_setup_and_cleanup_minutes_are_valid(): void
    {
        $resource = Resource::factory()->create([
            'setup_minutes' => 0,
            'cleanup_minutes' => 0,
        ]);

        $this->assertSame(0, $resource->fresh()->setup_minutes);
        $this->assertSame(0, $resource->fresh()->cleanup_minutes);
    }

    public function test_database_rejects_negative_setup_minutes(): void
    {
        $this->expectException(QueryException::class);

        Resource::factory()->create(['setup_minutes' => -1]);
    }

    public function test_database_rejects_negative_cleanup_minutes(): void
    {
        $this->expectException(QueryException::class);

        Resource::factory()->create(['cleanup_minutes' => -1]);
    }

    public function test_operating_hours_and_customer_bookable_hours_are_separate(): void
    {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->for($centre)->create();
        $operatingHour = CentreOperatingHour::factory()->for($centre)->create([
            'opens_at' => '08:00:00',
            'closes_at' => '22:00:00',
        ]);
        $bookableHour = FacilityBookableHour::factory()->for($facility)->create([
            'opens_at' => '09:00:00',
            'closes_at' => '21:00:00',
        ]);

        $this->assertNotSame($operatingHour->getTable(), $bookableHour->getTable());
        $this->assertSame('08:00:00', $centre->operatingHours->sole()->opens_at);
        $this->assertSame('09:00:00', $facility->bookableHours->sole()->opens_at);
    }
}
