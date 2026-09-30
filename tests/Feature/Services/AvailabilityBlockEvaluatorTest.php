<?php

namespace Tests\Feature\Services;

use App\Models\AvailabilityBlock;
use App\Models\Centre;
use App\Models\Facility;
use App\Models\Resource;
use App\Services\AvailabilityBlockEvaluator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AvailabilityBlockEvaluatorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AvailabilityBlockEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluator = new AvailabilityBlockEvaluator;
    }

    public function test_resource_with_no_block_is_not_blocked(): void
    {
        $fixture = $this->venueFixture();

        $isBlocked = $this->isBlocked($fixture['courtOne'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $this->assertFalse($isBlocked);
    }

    public function test_overlapping_resource_block_blocks_only_that_resource_in_one_query(): void
    {
        $fixture = $this->venueFixture();
        $this->blockResource($fixture['courtOne'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');
        DB::flushQueryLog();
        DB::enableQueryLog();

        $courtOneIsBlocked = $this->isBlocked($fixture['courtOne'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $this->assertTrue($courtOneIsBlocked);
        $this->assertCount(1, DB::getQueryLog());

        DB::disableQueryLog();

        $courtTwoIsBlocked = $this->isBlocked($fixture['courtTwo'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $this->assertFalse($courtTwoIsBlocked);
    }

    public function test_overlapping_facility_block_blocks_every_resource_in_that_facility(): void
    {
        $fixture = $this->venueFixture();
        $this->blockFacility($fixture['sportsHall'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $courtOneIsBlocked = $this->isBlocked($fixture['courtOne'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');
        $courtTwoIsBlocked = $this->isBlocked($fixture['courtTwo'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');
        $halfPitchIsBlocked = $this->isBlocked($fixture['halfPitch'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $this->assertTrue($courtOneIsBlocked);
        $this->assertTrue($courtTwoIsBlocked);
        $this->assertFalse($halfPitchIsBlocked);
    }

    public function test_overlapping_centre_block_blocks_every_resource_in_that_centre(): void
    {
        $fixture = $this->venueFixture();
        $this->blockCentre($fixture['hillside'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $courtOneIsBlocked = $this->isBlocked($fixture['courtOne'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');
        $courtTwoIsBlocked = $this->isBlocked($fixture['courtTwo'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');
        $halfPitchIsBlocked = $this->isBlocked($fixture['halfPitch'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $this->assertTrue($courtOneIsBlocked);
        $this->assertTrue($courtTwoIsBlocked);
        $this->assertFalse($halfPitchIsBlocked);
    }

    public function test_unrelated_resource_block_does_not_block_a_resource(): void
    {
        $fixture = $this->venueFixture();
        $this->blockResource($fixture['halfPitch'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $isBlocked = $this->isBlocked($fixture['courtOne'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $this->assertFalse($isBlocked);
    }

    public function test_unrelated_facility_block_does_not_block_a_resource(): void
    {
        $fixture = $this->venueFixture();
        $this->blockFacility($fixture['astro'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $isBlocked = $this->isBlocked($fixture['courtOne'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $this->assertFalse($isBlocked);
    }

    public function test_unrelated_centre_block_does_not_block_a_resource(): void
    {
        $fixture = $this->venueFixture();
        $this->blockCentre($fixture['riverside'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $isBlocked = $this->isBlocked($fixture['courtOne'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $this->assertFalse($isBlocked);
    }

    public function test_exactly_matching_block_blocks_a_resource(): void
    {
        $fixture = $this->venueFixture();
        $this->blockResource($fixture['courtOne'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $isBlocked = $this->isBlocked($fixture['courtOne'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $this->assertTrue($isBlocked);
    }

    public function test_request_starting_inside_a_block_is_blocked(): void
    {
        $fixture = $this->venueFixture();
        $this->blockResource($fixture['courtOne'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $isBlocked = $this->isBlocked($fixture['courtOne'], '2026-10-15 18:30:00', '2026-10-15 19:30:00');

        $this->assertTrue($isBlocked);
    }

    public function test_request_ending_inside_a_block_is_blocked(): void
    {
        $fixture = $this->venueFixture();
        $this->blockResource($fixture['courtOne'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $isBlocked = $this->isBlocked($fixture['courtOne'], '2026-10-15 17:30:00', '2026-10-15 18:30:00');

        $this->assertTrue($isBlocked);
    }

    public function test_request_containing_a_block_is_blocked(): void
    {
        $fixture = $this->venueFixture();
        $this->blockResource($fixture['courtOne'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $isBlocked = $this->isBlocked($fixture['courtOne'], '2026-10-15 17:00:00', '2026-10-15 20:00:00');

        $this->assertTrue($isBlocked);
    }

    public function test_request_contained_by_a_block_is_blocked(): void
    {
        $fixture = $this->venueFixture();
        $this->blockResource($fixture['courtOne'], '2026-10-15 18:00:00', '2026-10-15 20:00:00');

        $isBlocked = $this->isBlocked($fixture['courtOne'], '2026-10-15 18:30:00', '2026-10-15 19:00:00');

        $this->assertTrue($isBlocked);
    }

    public function test_request_ending_when_a_block_starts_is_not_blocked(): void
    {
        $fixture = $this->venueFixture();
        $this->blockResource($fixture['courtOne'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $isBlocked = $this->isBlocked($fixture['courtOne'], '2026-10-15 17:00:00', '2026-10-15 18:00:00');

        $this->assertFalse($isBlocked);
    }

    public function test_request_starting_when_a_block_ends_is_not_blocked(): void
    {
        $fixture = $this->venueFixture();
        $this->blockResource($fixture['courtOne'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $isBlocked = $this->isBlocked($fixture['courtOne'], '2026-10-15 19:00:00', '2026-10-15 20:00:00');

        $this->assertFalse($isBlocked);
    }

    public function test_cross_midnight_request_overlapping_a_block_is_blocked(): void
    {
        $fixture = $this->venueFixture();
        $this->blockResource($fixture['courtOne'], '2026-10-15 23:30:00', '2026-10-16 00:30:00');

        $isBlocked = $this->isBlocked($fixture['courtOne'], '2026-10-15 23:45:00', '2026-10-16 00:15:00');

        $this->assertTrue($isBlocked);
    }

    public function test_multi_day_block_blocks_a_contained_request(): void
    {
        $fixture = $this->venueFixture();
        $this->blockCentre($fixture['hillside'], '2026-10-15 18:00:00', '2026-10-17 09:00:00');

        $isBlocked = $this->isBlocked($fixture['courtOne'], '2026-10-16 10:00:00', '2026-10-16 11:00:00');

        $this->assertTrue($isBlocked);
    }

    public function test_zero_duration_request_is_not_blocked(): void
    {
        $fixture = $this->venueFixture();
        $this->blockResource($fixture['courtOne'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $isBlocked = $this->isBlocked($fixture['courtOne'], '2026-10-15 18:00:00', '2026-10-15 18:00:00');

        $this->assertFalse($isBlocked);
    }

    public function test_reversed_request_is_not_blocked(): void
    {
        $fixture = $this->venueFixture();
        $this->blockResource($fixture['courtOne'], '2026-10-15 18:00:00', '2026-10-15 19:00:00');

        $isBlocked = $this->isBlocked($fixture['courtOne'], '2026-10-15 19:00:00', '2026-10-15 18:00:00');

        $this->assertFalse($isBlocked);
    }

    /**
     * @return array{hillside: Centre, riverside: Centre, sportsHall: Facility, astro: Facility, courtOne: resource, courtTwo: resource, halfPitch: resource}
     */
    private function venueFixture(): array
    {
        $hillside = Centre::factory()->create(['name' => 'Hillside', 'slug' => 'hillside']);
        $riverside = Centre::factory()->create(['name' => 'Riverside', 'slug' => 'riverside']);
        $sportsHall = Facility::factory()->for($hillside)->create(['name' => 'Sports Hall', 'slug' => 'sports-hall']);
        $astro = Facility::factory()->for($riverside)->create(['name' => 'Astro', 'slug' => 'astro']);
        $courtOne = Resource::factory()->for($sportsHall)->create(['name' => 'Court 1', 'slug' => 'court-1']);
        $courtTwo = Resource::factory()->for($sportsHall)->create(['name' => 'Court 2', 'slug' => 'court-2']);
        $halfPitch = Resource::factory()->for($astro)->create(['name' => 'Half Pitch', 'slug' => 'half-pitch']);

        return compact('hillside', 'riverside', 'sportsHall', 'astro', 'courtOne', 'courtTwo', 'halfPitch');
    }

    private function blockCentre(Centre $centre, string $startsAt, string $endsAt): AvailabilityBlock
    {
        return AvailabilityBlock::factory()->forCentre($centre)->create(['starts_at' => $startsAt, 'ends_at' => $endsAt]);
    }

    private function blockFacility(Facility $facility, string $startsAt, string $endsAt): AvailabilityBlock
    {
        return AvailabilityBlock::factory()->forFacility($facility)->create(['starts_at' => $startsAt, 'ends_at' => $endsAt]);
    }

    private function blockResource(Resource $resource, string $startsAt, string $endsAt): AvailabilityBlock
    {
        return AvailabilityBlock::factory()->forResource($resource)->create(['starts_at' => $startsAt, 'ends_at' => $endsAt]);
    }

    private function isBlocked(Resource $resource, string $startsAt, string $endsAt): bool
    {
        return $this->evaluator->isBlocked($resource, $this->at($startsAt), $this->at($endsAt));
    }

    private function at(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, config('app.timezone'));
    }
}
