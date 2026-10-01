<?php

namespace Tests\Feature\Services;

use App\Enums\EquipmentChargeType;
use App\Enums\PricingFailureReason;
use App\Models\Centre;
use App\Models\Equipment;
use App\Models\EquipmentRate;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\ResourceRate;
use App\Models\User;
use App\Services\EquipmentRequirement;
use App\Services\PricingContext;
use App\Services\PricingDiscount;
use App\Services\PricingOverride;
use App\Services\PricingQuoteResult;
use App\Services\PricingRequest;
use App\Services\PricingService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemRoleSeeder::class);
    }

    public function test_returns_an_hourly_resource_quote_with_an_explainable_breakdown(): void
    {
        $resource = $this->resource();
        ResourceRate::factory()->for($resource)->create(['amount_minor' => 2500]);

        $result = $this->quote($resource);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('GBP', $result->quote->currency);
        $this->assertSame(2500, $result->quote->baseResourceAmountMinor);
        $this->assertSame(0, $result->quote->equipmentAmountMinor);
        $this->assertSame(2500, $result->quote->subtotalMinor);
        $this->assertSame(2500, $result->quote->calculatedTotalMinor);
        $this->assertSame(2500, $result->quote->finalTotalMinor);
        $this->assertSame('Resource', $result->quote->resourceLine->resourceName);
        $this->assertSame(3600, $result->quote->resourceLine->durationSeconds);
    }

    public function test_uses_resource_specific_rates_at_different_centres(): void
    {
        $firstResource = $this->resource();
        $secondResource = $this->resource();
        ResourceRate::factory()->for($firstResource)->create(['amount_minor' => 1800]);
        ResourceRate::factory()->for($secondResource)->create(['amount_minor' => 4250]);

        $this->assertSame(1800, $this->quote($firstResource)->quote->finalTotalMinor);
        $this->assertSame(4250, $this->quote($secondResource)->quote->finalTotalMinor);
        $this->assertNotSame($firstResource->facility->centre_id, $secondResource->facility->centre_id);
    }

    public function test_calculates_thirty_and_ninety_minute_durations_from_the_hourly_rate(): void
    {
        $resource = $this->resource();
        ResourceRate::factory()->for($resource)->create(['amount_minor' => 2500]);

        $thirtyMinutes = $this->quote($resource, '2026-06-15 10:00:00', '2026-06-15 10:30:00');
        $ninetyMinutes = $this->quote($resource, '2026-06-15 10:00:00', '2026-06-15 11:30:00');

        $this->assertSame(1250, $thirtyMinutes->quote->finalTotalMinor);
        $this->assertSame(3750, $ninetyMinutes->quote->finalTotalMinor);
    }

    public function test_rounds_fractional_minor_units_to_nearest_unit_with_halves_up(): void
    {
        $resource = $this->resource();
        ResourceRate::factory()->for($resource)->create(['amount_minor' => 1]);

        $lessThanHalf = $this->quote($resource, '2026-06-15 10:00:00', '2026-06-15 10:29:59');
        $half = $this->quote($resource, '2026-06-15 10:00:00', '2026-06-15 10:30:00');

        $this->assertSame(0, $lessThanHalf->quote->finalTotalMinor);
        $this->assertSame(1, $half->quote->finalTotalMinor);
    }

    public function test_uses_the_booking_start_date_with_inclusive_effective_boundaries(): void
    {
        $resource = $this->resource();
        ResourceRate::factory()->for($resource)->create([
            'amount_minor' => 1500,
            'effective_from' => '2026-06-01',
            'effective_until' => '2026-06-30',
        ]);

        $fromBoundary = $this->quote($resource, '2026-06-01 10:00:00', '2026-06-01 11:00:00');
        $untilBoundary = $this->quote($resource, '2026-06-30 10:00:00', '2026-06-30 11:00:00');

        $this->assertSame(1500, $fromBoundary->quote->finalTotalMinor);
        $this->assertSame(1500, $untilBoundary->quote->finalTotalMinor);
    }

    public function test_selects_the_future_effective_rate_when_its_period_begins(): void
    {
        $resource = $this->resource();
        ResourceRate::factory()->for($resource)->create([
            'amount_minor' => 2000,
            'effective_from' => '2026-01-01',
            'effective_until' => '2026-06-30',
        ]);
        ResourceRate::factory()->for($resource)->create([
            'amount_minor' => 2750,
            'effective_from' => '2026-07-01',
        ]);

        $result = $this->quote($resource, '2026-07-01 10:00:00', '2026-07-01 11:00:00');

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(2750, $result->quote->finalTotalMinor);
    }

    public function test_returns_missing_resource_rate_without_selecting_an_arbitrary_rate(): void
    {
        $result = $this->quote($this->resource());

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(PricingFailureReason::MissingResourceRate, $result->failure->reason);
    }

    public function test_rejects_overlapping_resource_rates_as_ambiguous(): void
    {
        $resource = $this->resource();
        ResourceRate::factory()->for($resource)->create(['effective_from' => '2026-01-01']);
        ResourceRate::factory()->for($resource)->create(['effective_from' => '2026-06-01']);

        $result = $this->quote($resource);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(PricingFailureReason::AmbiguousResourceRate, $result->failure->reason);
    }

    public function test_included_equipment_contributes_zero_but_remains_in_the_breakdown(): void
    {
        $resource = $this->pricedResource();
        $equipment = Equipment::factory()->for($resource->facility->centre)->create(['name' => 'Nets']);
        EquipmentRate::factory()->for($equipment)->included()->create();

        $result = $this->quote($resource, equipmentRequirements: [new EquipmentRequirement($equipment, 2)]);

        $this->assertSame(2500, $result->quote->finalTotalMinor);
        $this->assertSame(0, $result->quote->equipmentAmountMinor);
        $this->assertSame(1, count($result->quote->equipmentLines));
        $this->assertSame(EquipmentChargeType::Included, $result->quote->equipmentLines[0]->chargeType);
        $this->assertSame(0, $result->quote->equipmentLines[0]->amountMinor);
    }

    public function test_calculates_separately_chargeable_equipment_by_quantity_and_duration(): void
    {
        $resource = $this->pricedResource();
        $equipment = Equipment::factory()->for($resource->facility->centre)->create(['name' => 'Goals']);
        EquipmentRate::factory()->for($equipment)->create(['amount_minor' => 500]);

        $result = $this->quote(
            $resource,
            '2026-06-15 10:00:00',
            '2026-06-15 11:30:00',
            [new EquipmentRequirement($equipment, 3)],
        );

        $this->assertSame(2250, $result->quote->equipmentAmountMinor);
        $this->assertSame(6000, $result->quote->finalTotalMinor);
        $this->assertSame(3, $result->quote->equipmentLines[0]->quantity);
        $this->assertSame(2250, $result->quote->equipmentLines[0]->amountMinor);
    }

    public function test_returns_missing_and_ambiguous_equipment_rate_failures(): void
    {
        $resource = $this->pricedResource();
        $equipment = Equipment::factory()->for($resource->facility->centre)->create();

        $missing = $this->quote($resource, equipmentRequirements: [new EquipmentRequirement($equipment, 1)]);
        EquipmentRate::factory()->for($equipment)->create(['effective_from' => '2026-01-01']);
        EquipmentRate::factory()->for($equipment)->create(['effective_from' => '2026-06-01']);
        $ambiguous = $this->quote($resource, equipmentRequirements: [new EquipmentRequirement($equipment, 1)]);

        $this->assertSame(PricingFailureReason::MissingEquipmentRate, $missing->failure->reason);
        $this->assertSame(PricingFailureReason::AmbiguousEquipmentRate, $ambiguous->failure->reason);
    }

    public function test_calculates_multiple_equipment_lines_and_reconciles_their_totals(): void
    {
        $resource = $this->pricedResource();
        $firstEquipment = Equipment::factory()->for($resource->facility->centre)->create();
        $secondEquipment = Equipment::factory()->for($resource->facility->centre)->create();
        EquipmentRate::factory()->for($firstEquipment)->create(['amount_minor' => 300]);
        EquipmentRate::factory()->for($secondEquipment)->create(['amount_minor' => 700]);

        $result = $this->quote($resource, equipmentRequirements: [
            new EquipmentRequirement($firstEquipment, 2),
            new EquipmentRequirement($secondEquipment, 1),
        ]);

        $this->assertSame(1300, $result->quote->equipmentAmountMinor);
        $this->assertSame(3800, $result->quote->subtotalMinor);
        $this->assertSame(3800, $result->quote->calculatedTotalMinor);
        $this->assertSame(3800, $result->quote->finalTotalMinor);
        $this->assertSame(1300, $result->quote->equipmentLines[0]->amountMinor + $result->quote->equipmentLines[1]->amountMinor);
    }

    public function test_returns_a_currency_mismatch_when_an_equipment_rate_is_incompatible(): void
    {
        $resource = $this->pricedResource();
        $equipment = Equipment::factory()->for($resource->facility->centre)->create();
        EquipmentRate::factory()->for($equipment)->create(['currency' => 'EUR']);

        $result = $this->quote($resource, equipmentRequirements: [new EquipmentRequirement($equipment, 1)]);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(PricingFailureReason::CurrencyMismatch, $result->failure->reason);
    }

    public function test_applies_a_fixed_discount_without_allowing_a_negative_total(): void
    {
        $resource = $this->pricedResource();

        $result = $this->quote($resource, context: new PricingContext(
            discount: new PricingDiscount(3000, 'Negotiated community rate'),
        ));

        $this->assertSame(2500, $result->quote->discount->amountMinor);
        $this->assertSame(3000, $result->quote->discount->requestedAmountMinor);
        $this->assertSame(0, $result->quote->calculatedTotalMinor);
        $this->assertSame(0, $result->quote->finalTotalMinor);
    }

    public function test_default_pricing_does_not_depend_on_a_customer_classification(): void
    {
        $resource = $this->pricedResource();

        $result = $this->quote($resource, context: new PricingContext(customerClassification: 'community_group'));

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(2500, $result->quote->finalTotalMinor);
    }

    public function test_applies_an_authorised_manager_override_without_persisting_an_audit_entry(): void
    {
        $resource = $this->pricedResource();
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->assignedCentres()->attach($resource->facility->centre);
        $adjustedAt = CarbonImmutable::parse('2026-06-01 09:30:00', config('app.timezone'));

        $result = $this->quote($resource, context: new PricingContext(
            override: new PricingOverride($manager, 1900, 'Community event agreement', $adjustedAt),
        ));

        $this->assertSame(2500, $result->quote->calculatedTotalMinor);
        $this->assertSame(1900, $result->quote->finalTotalMinor);
        $this->assertSame(2500, $result->quote->override->originalTotalMinor);
        $this->assertSame(1900, $result->quote->override->adjustedTotalMinor);
        $this->assertSame($manager->id, $result->quote->override->responsibleUserId);
        $this->assertSame($manager->name, $result->quote->override->responsibleUserName);
        $this->assertTrue($result->quote->override->adjustedAt->equalTo($adjustedAt));

        $this->assertDatabaseEmpty('activity_log');
    }

    public function test_rejects_overrides_without_permission_or_centre_access(): void
    {
        $resource = $this->pricedResource();
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        $managerWithoutAccess = User::factory()->create();
        $managerWithoutAccess->assignRole('manager');

        $withoutPermission = $this->quote($resource, context: new PricingContext(
            override: new PricingOverride($customer, 1000, 'Reason', CarbonImmutable::parse('2026-06-01 09:30:00', config('app.timezone'))),
        ));
        $withoutAccess = $this->quote($resource, context: new PricingContext(
            override: new PricingOverride($managerWithoutAccess, 1000, 'Reason', CarbonImmutable::parse('2026-06-01 09:30:00', config('app.timezone'))),
        ));

        $this->assertSame(PricingFailureReason::UnauthorizedOverride, $withoutPermission->failure->reason);
        $this->assertSame(PricingFailureReason::UnauthorizedOverride, $withoutAccess->failure->reason);
        $this->assertDatabaseEmpty('activity_log');
    }

    public function test_rejects_overrides_without_a_reason_or_with_a_negative_total(): void
    {
        $resource = $this->pricedResource();
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->assignedCentres()->attach($resource->facility->centre);

        $withoutReason = $this->quote($resource, context: new PricingContext(
            override: new PricingOverride($manager, 1000, '   ', CarbonImmutable::parse('2026-06-01 09:30:00', config('app.timezone'))),
        ));
        $negativeAmount = $this->quote($resource, context: new PricingContext(
            override: new PricingOverride($manager, -1, 'Reason', CarbonImmutable::parse('2026-06-01 09:30:00', config('app.timezone'))),
        ));

        $this->assertSame(PricingFailureReason::InvalidOverride, $withoutReason->failure->reason);
        $this->assertSame(PricingFailureReason::InvalidOverride, $negativeAmount->failure->reason);
    }

    public function test_rejects_a_non_positive_duration_and_does_not_mutate_caller_carbon_objects(): void
    {
        $resource = $this->pricedResource();
        $startsAt = Carbon::parse('2026-06-15 10:00:00', 'UTC');
        $endsAt = Carbon::parse('2026-06-15 10:00:00', 'UTC');

        $result = app(PricingService::class)->quote(new PricingRequest($resource, $startsAt, $endsAt));

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(PricingFailureReason::InvalidRequest, $result->failure->reason);
        $this->assertSame('UTC', $startsAt->getTimezone()->getName());
        $this->assertSame('2026-06-15 10:00:00', $startsAt->toDateTimeString());
    }

    private function pricedResource(): Resource
    {
        $resource = $this->resource();
        ResourceRate::factory()->for($resource)->create(['amount_minor' => 2500]);

        return $resource;
    }

    private function resource(): Resource
    {
        $centre = Centre::factory()->create();

        return Resource::factory()->for(Facility::factory()->for($centre))->create(['name' => 'Resource']);
    }

    /**
     * @param  list<EquipmentRequirement>  $equipmentRequirements
     */
    private function quote(
        Resource $resource,
        string $startsAt = '2026-06-15 10:00:00',
        string $endsAt = '2026-06-15 11:00:00',
        array $equipmentRequirements = [],
        ?PricingContext $context = null,
    ): PricingQuoteResult {
        return app(PricingService::class)->quote(new PricingRequest(
            $resource,
            CarbonImmutable::parse($startsAt, config('app.timezone')),
            CarbonImmutable::parse($endsAt, config('app.timezone')),
            $equipmentRequirements,
            $context,
        ));
    }
}
