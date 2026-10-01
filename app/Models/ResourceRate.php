<?php

namespace App\Models;

use App\Enums\PricingRateUnit;
use Carbon\CarbonImmutable;
use Database\Factories\ResourceRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $resource_id
 * @property int $amount_minor
 * @property string $currency
 * @property PricingRateUnit $rate_unit
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_until
 */
#[Fillable(['resource_id', 'amount_minor', 'currency', 'rate_unit', 'effective_from', 'effective_until'])]
class ResourceRate extends Model
{
    /** @use HasFactory<ResourceRateFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<\App\Models\Resource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'rate_unit' => PricingRateUnit::class,
            'effective_from' => 'immutable_date',
            'effective_until' => 'immutable_date',
        ];
    }
}
