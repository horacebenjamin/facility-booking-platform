<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\BookingPriceSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $booking_id
 * @property string $currency
 * @property int $resource_amount_minor
 * @property int $equipment_amount_minor
 * @property int $subtotal_minor
 * @property int|null $discount_requested_amount_minor
 * @property int $discount_amount_minor
 * @property string|null $discount_description
 * @property int $calculated_total_minor
 * @property int $final_total_minor
 * @property int|null $override_original_total_minor
 * @property int|null $override_adjusted_total_minor
 * @property string|null $override_reason
 * @property int|null $override_responsible_user_id
 * @property string|null $override_responsible_user_name
 * @property CarbonInterface|null $override_adjusted_at
 */
#[Fillable(['booking_id', 'currency', 'resource_amount_minor', 'equipment_amount_minor', 'subtotal_minor', 'discount_requested_amount_minor', 'discount_amount_minor', 'discount_description', 'calculated_total_minor', 'final_total_minor', 'override_original_total_minor', 'override_adjusted_total_minor', 'override_reason', 'override_responsible_user_id', 'override_responsible_user_name', 'override_adjusted_at'])]
class BookingPriceSnapshot extends Model
{
    /** @use HasFactory<BookingPriceSnapshotFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function overrideResponsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_responsible_user_id');
    }

    /**
     * @return HasMany<BookingPriceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BookingPriceLine::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resource_amount_minor' => 'integer',
            'equipment_amount_minor' => 'integer',
            'subtotal_minor' => 'integer',
            'discount_requested_amount_minor' => 'integer',
            'discount_amount_minor' => 'integer',
            'calculated_total_minor' => 'integer',
            'final_total_minor' => 'integer',
            'override_original_total_minor' => 'integer',
            'override_adjusted_total_minor' => 'integer',
            'override_responsible_user_id' => 'integer',
            'override_adjusted_at' => 'datetime',
        ];
    }
}
