<?php

namespace Tests\Feature\Services;

use App\Enums\DayOfWeek;
use App\Models\Facility;
use App\Models\FacilityBookableHour;
use App\Models\Resource;
use App\Models\ResourceBookableHour;
use App\Services\BookableHoursEvaluator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BookableHoursEvaluatorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private BookableHoursEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluator = new BookableHoursEvaluator;
    }

    public function test_facility_contains_a_request_fully_inside_configured_hours(): void
    {
        $facility = $this->facilityWithMondayHours();

        $containsRequest = $this->evaluator->facilityContainsRequest($facility, $this->at('2026-10-05 09:00:00'), $this->at('2026-10-05 10:00:00'));

        $this->assertTrue($containsRequest);
    }

    public function test_facility_contains_a_request_starting_exactly_at_opening(): void
    {
        $facility = $this->facilityWithMondayHours();

        $containsRequest = $this->evaluator->facilityContainsRequest($facility, $this->at('2026-10-05 08:00:00'), $this->at('2026-10-05 09:00:00'));

        $this->assertTrue($containsRequest);
    }

    public function test_facility_contains_a_request_ending_exactly_at_closing(): void
    {
        $facility = $this->facilityWithMondayHours();

        $containsRequest = $this->evaluator->facilityContainsRequest($facility, $this->at('2026-10-05 20:00:00'), $this->at('2026-10-05 21:00:00'));

        $this->assertTrue($containsRequest);
    }

    public function test_facility_contains_a_request_covering_its_entire_configured_range(): void
    {
        $facility = $this->facilityWithMondayHours();

        $containsRequest = $this->evaluator->facilityContainsRequest($facility, $this->at('2026-10-05 08:00:00'), $this->at('2026-10-05 21:00:00'));

        $this->assertTrue($containsRequest);
    }

    public function test_facility_rejects_a_request_starting_before_opening(): void
    {
        $facility = $this->facilityWithMondayHours();

        $containsRequest = $this->evaluator->facilityContainsRequest($facility, $this->at('2026-10-05 07:59:00'), $this->at('2026-10-05 09:00:00'));

        $this->assertFalse($containsRequest);
    }

    public function test_facility_rejects_a_request_ending_after_closing(): void
    {
        $facility = $this->facilityWithMondayHours();

        $containsRequest = $this->evaluator->facilityContainsRequest($facility, $this->at('2026-10-05 20:00:00'), $this->at('2026-10-05 21:01:00'));

        $this->assertFalse($containsRequest);
    }

    public function test_facility_rejects_a_request_entirely_before_opening(): void
    {
        $facility = $this->facilityWithMondayHours();

        $containsRequest = $this->evaluator->facilityContainsRequest($facility, $this->at('2026-10-05 07:00:00'), $this->at('2026-10-05 08:00:00'));

        $this->assertFalse($containsRequest);
    }

    public function test_facility_rejects_a_request_entirely_after_closing(): void
    {
        $facility = $this->facilityWithMondayHours();

        $containsRequest = $this->evaluator->facilityContainsRequest($facility, $this->at('2026-10-05 21:00:00'), $this->at('2026-10-05 22:00:00'));

        $this->assertFalse($containsRequest);
    }

    public function test_facility_rejects_a_request_without_hours_for_its_weekday(): void
    {
        $facility = $this->facilityWithMondayHours();

        $containsRequest = $this->evaluator->facilityContainsRequest($facility, $this->at('2026-10-06 09:00:00'), $this->at('2026-10-06 10:00:00'));

        $this->assertFalse($containsRequest);
    }

    public function test_facility_uses_the_hours_configured_for_the_requested_weekday(): void
    {
        $facility = $this->facilityWithMondayHours();
        FacilityBookableHour::factory()->for($facility)->create([
            'day_of_week' => DayOfWeek::Tuesday,
            'opens_at' => '10:00:00',
            'closes_at' => '16:00:00',
        ]);

        $containsRequest = $this->evaluator->facilityContainsRequest($facility, $this->at('2026-10-06 09:00:00'), $this->at('2026-10-06 10:00:00'));

        $this->assertFalse($containsRequest);
    }

    public function test_facility_rejects_a_zero_duration_request(): void
    {
        $facility = $this->facilityWithMondayHours();

        $containsRequest = $this->evaluator->facilityContainsRequest($facility, $this->at('2026-10-05 09:00:00'), $this->at('2026-10-05 09:00:00'));

        $this->assertFalse($containsRequest);
    }

    public function test_facility_rejects_a_reversed_request(): void
    {
        $facility = $this->facilityWithMondayHours();

        $containsRequest = $this->evaluator->facilityContainsRequest($facility, $this->at('2026-10-05 10:00:00'), $this->at('2026-10-05 09:00:00'));

        $this->assertFalse($containsRequest);
    }

    public function test_facility_rejects_a_request_spanning_midnight(): void
    {
        $facility = $this->facilityWithMondayHours();

        $containsRequest = $this->evaluator->facilityContainsRequest($facility, $this->at('2026-10-05 20:00:00'), $this->at('2026-10-06 08:00:00'));

        $this->assertFalse($containsRequest);
    }

    public function test_facility_and_resource_bookable_hours_are_evaluated_independently(): void
    {
        $facility = $this->facilityWithMondayHours();
        $resource = Resource::factory()->for($facility)->create();
        ResourceBookableHour::factory()->for($resource)->create([
            'day_of_week' => DayOfWeek::Monday,
            'opens_at' => '10:00:00',
            'closes_at' => '18:00:00',
        ]);

        $facilityContainsRequest = $this->evaluator->facilityContainsRequest($facility, $this->at('2026-10-05 09:00:00'), $this->at('2026-10-05 10:00:00'));
        $resourceContainsRequest = $this->evaluator->resourceContainsRequest($resource, $this->at('2026-10-05 09:00:00'), $this->at('2026-10-05 10:00:00'));

        $this->assertTrue($facilityContainsRequest);
        $this->assertFalse($resourceContainsRequest);
    }

    public function test_resource_contains_a_request_inside_its_own_configured_hours(): void
    {
        $resource = Resource::factory()->create();
        ResourceBookableHour::factory()->for($resource)->create([
            'day_of_week' => DayOfWeek::Tuesday,
            'opens_at' => '10:00:00',
            'closes_at' => '18:00:00',
        ]);

        $containsRequest = $this->evaluator->resourceContainsRequest($resource, $this->at('2026-10-06 10:00:00'), $this->at('2026-10-06 18:00:00'));

        $this->assertTrue($containsRequest);
    }

    public function test_bst_request_at_six_pm_is_checked_as_six_pm_local_time(): void
    {
        $facility = $this->facilityWithMondayHours();

        $containsRequest = $this->evaluator->facilityContainsRequest(
            $facility,
            $this->at('2026-10-05 18:00:00'),
            $this->at('2026-10-05 19:00:00'),
        );

        $this->assertTrue($containsRequest);
        $this->assertSame('2026-10-05 17:00:00', $this->at('2026-10-05 18:00:00')->toDateTimeString());
    }

    public function test_gmt_request_at_six_pm_is_checked_as_six_pm_local_time(): void
    {
        $facility = $this->facilityWithMondayHours();

        $containsRequest = $this->evaluator->facilityContainsRequest(
            $facility,
            $this->at('2027-01-04 18:00:00'),
            $this->at('2027-01-04 19:00:00'),
        );

        $this->assertTrue($containsRequest);
        $this->assertSame('2027-01-04 18:00:00', $this->at('2027-01-04 18:00:00')->toDateTimeString());
    }

    private function facilityWithMondayHours(): Facility
    {
        $facility = Facility::factory()->create();
        FacilityBookableHour::factory()->for($facility)->create([
            'day_of_week' => DayOfWeek::Monday,
            'opens_at' => '08:00:00',
            'closes_at' => '21:00:00',
        ]);

        return $facility;
    }

    private function at(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, config('booking.local_timezone'))
            ->setTimezone(config('app.timezone'));
    }
}
