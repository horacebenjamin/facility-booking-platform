<?php

namespace App\Models;

use App\Enums\EquipmentChargeType;
use App\Enums\PricingRateUnit;
use Carbon\CarbonImmutable;
use Database\Factories\EquipmentRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $equipment_id
 * @property EquipmentChargeType $charge_type
 * @property int $amount_minor
 * @property string $currency
 * @property PricingRateUnit $rate_unit
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_until
 */
#[Fillable(['equipment_id', 'charge_type', 'amount_minor', 'currency', 'rate_unit', 'effective_from', 'effective_until'])]
class EquipmentRate extends Model
{
    /** @use HasFactory<EquipmentRateFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'charge_type' => EquipmentChargeType::class,
            'amount_minor' => 'integer',
            'rate_unit' => PricingRateUnit::class,
            'effective_from' => 'immutable_date',
            'effective_until' => 'immutable_date',
        ];
    }
}
