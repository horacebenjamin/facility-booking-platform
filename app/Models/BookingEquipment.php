<?php

namespace App\Models;

use Database\Factories\BookingEquipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $booking_id
 * @property int $centre_id
 * @property int $equipment_id
 * @property int $requested_quantity
 */
#[Fillable(['booking_id', 'centre_id', 'equipment_id', 'requested_quantity'])]
class BookingEquipment extends Model
{
    /** @use HasFactory<BookingEquipmentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * @return HasOne<BookingPriceLine, $this>
     */
    public function priceLine(): HasOne
    {
        return $this->hasOne(BookingPriceLine::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_quantity' => 'integer',
        ];
    }
}
