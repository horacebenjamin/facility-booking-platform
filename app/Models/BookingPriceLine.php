<?php

namespace App\Models;

use App\Enums\BookingPriceLineType;
use App\Enums\EquipmentChargeType;
use App\Enums\PricingRateUnit;
use Carbon\CarbonImmutable;
use Database\Factories\BookingPriceLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $booking_price_snapshot_id
 * @property int $booking_id
 * @property int|null $booking_equipment_id
 * @property BookingPriceLineType $line_type
 * @property string $description
 * @property int $quantity
 * @property EquipmentChargeType|null $charge_type
 * @property int $hourly_rate_minor
 * @property PricingRateUnit $rate_unit
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_until
 * @property int $duration_seconds
 * @property int $amount_minor
 */
#[Fillable(['booking_price_snapshot_id', 'booking_id', 'booking_equipment_id', 'line_type', 'description', 'quantity', 'charge_type', 'hourly_rate_minor', 'rate_unit', 'effective_from', 'effective_until', 'duration_seconds', 'amount_minor'])]
class BookingPriceLine extends Model
{
    /** @use HasFactory<BookingPriceLineFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<BookingPriceSnapshot, $this>
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(BookingPriceSnapshot::class, 'booking_price_snapshot_id');
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<BookingEquipment, $this>
     */
    public function equipmentRequest(): BelongsTo
    {
        return $this->belongsTo(BookingEquipment::class, 'booking_equipment_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'line_type' => BookingPriceLineType::class,
            'quantity' => 'integer',
            'charge_type' => EquipmentChargeType::class,
            'hourly_rate_minor' => 'integer',
            'rate_unit' => PricingRateUnit::class,
            'effective_from' => 'immutable_date',
            'effective_until' => 'immutable_date',
            'duration_seconds' => 'integer',
            'amount_minor' => 'integer',
        ];
    }
}
