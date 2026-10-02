<?php

namespace Database\Factories;

use App\Enums\BookingPriceLineType;
use App\Enums\EquipmentChargeType;
use App\Enums\PricingRateUnit;
use App\Models\BookingEquipment;
use App\Models\BookingPriceLine;
use App\Models\BookingPriceSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingPriceLine>
 */
class BookingPriceLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_price_snapshot_id' => BookingPriceSnapshot::factory(),
            'booking_id' => static function (array $attributes): int {
                return BookingPriceSnapshot::query()->whereKey($attributes['booking_price_snapshot_id'])->sole()->booking_id;
            },
            'booking_equipment_id' => null,
            'line_type' => BookingPriceLineType::Resource,
            'description' => 'Sports Hall',
            'quantity' => 1,
            'charge_type' => null,
            'hourly_rate_minor' => 2500,
            'rate_unit' => PricingRateUnit::Hourly,
            'effective_from' => '2026-01-01',
            'effective_until' => null,
            'duration_seconds' => 7200,
            'amount_minor' => 5000,
        ];
    }

    public function equipment(BookingEquipment $equipmentRequest): static
    {
        return $this->state(fn (array $attributes): array => [
            'booking_equipment_id' => $equipmentRequest->id,
            'line_type' => BookingPriceLineType::Equipment,
            'description' => $equipmentRequest->equipment->name,
            'quantity' => $equipmentRequest->requested_quantity,
            'charge_type' => EquipmentChargeType::SeparatelyChargeable,
            'hourly_rate_minor' => 500,
            'amount_minor' => 1000 * $equipmentRequest->requested_quantity,
        ]);
    }

    public function includedEquipment(BookingEquipment $equipmentRequest): static
    {
        return $this->equipment($equipmentRequest)->state(fn (array $attributes): array => [
            'charge_type' => EquipmentChargeType::Included,
            'hourly_rate_minor' => 0,
            'amount_minor' => 0,
        ]);
    }
}
