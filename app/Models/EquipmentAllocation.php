<?php

namespace App\Models;

use Database\Factories\EquipmentAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $booking_id
 * @property int|null $booking_equipment_id
 * @property int $equipment_id
 * @property int $quantity
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $expires_at
 */
#[Fillable(['booking_id', 'booking_equipment_id', 'equipment_id', 'quantity', 'starts_at', 'ends_at', 'expires_at'])]
class EquipmentAllocation extends Model
{
    /** @use HasFactory<EquipmentAllocationFactory> */
    use HasFactory;

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
    public function bookingEquipment(): BelongsTo
    {
        return $this->belongsTo(BookingEquipment::class);
    }

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
            'quantity' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
