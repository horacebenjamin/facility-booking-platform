<?php

namespace Tests\Unit\Services;

use App\Enums\AvailabilityReason;
use App\Services\AvailabilityResult;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AvailabilityResultTest extends TestCase
{
    public function test_result_with_no_reasons_is_available(): void
    {
        $result = new AvailabilityResult;

        $this->assertTrue($result->isAvailable());
        $this->assertSame([], $result->reasons());
    }

    public function test_result_with_a_reason_is_unavailable(): void
    {
        $result = new AvailabilityResult(AvailabilityReason::Blockout);

        $this->assertFalse($result->isAvailable());
        $this->assertSame([AvailabilityReason::Blockout], $result->reasons());
        $this->assertTrue($result->hasReason(AvailabilityReason::Blockout));
        $this->assertFalse($result->hasReason(AvailabilityReason::ResourceConflict));
    }

    public function test_result_preserves_reason_order_and_deduplicates_reasons(): void
    {
        $result = new AvailabilityResult(
            AvailabilityReason::Blockout,
            AvailabilityReason::ResourceConflict,
            AvailabilityReason::Blockout,
        );

        $this->assertFalse($result->isAvailable());
        $this->assertSame([
            AvailabilityReason::Blockout,
            AvailabilityReason::ResourceConflict,
        ], $result->reasons());
    }

    #[DataProvider('reasons')]
    public function test_reason_codes_have_stable_machine_readable_values(AvailabilityReason $reason, string $value): void
    {
        $this->assertSame($value, $reason->value);
    }

    /**
     * @return array<string, array{AvailabilityReason, string}>
     */
    public static function reasons(): array
    {
        return [
            'invalid period' => [AvailabilityReason::InvalidPeriod, 'invalid_period'],
            'inactive centre' => [AvailabilityReason::InactiveCentre, 'inactive_centre'],
            'inactive facility' => [AvailabilityReason::InactiveFacility, 'inactive_facility'],
            'inactive resource' => [AvailabilityReason::InactiveResource, 'inactive_resource'],
            'outside bookable hours' => [AvailabilityReason::OutsideBookableHours, 'outside_bookable_hours'],
            'resource conflict' => [AvailabilityReason::ResourceConflict, 'resource_conflict'],
            'blockout' => [AvailabilityReason::Blockout, 'blockout'],
            'equipment unavailable' => [AvailabilityReason::EquipmentUnavailable, 'equipment_unavailable'],
        ];
    }
}
