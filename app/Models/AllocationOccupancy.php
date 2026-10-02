<?php

namespace App\Models;

use Database\Factories\AllocationOccupancyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $booking_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $expires_at
 */
#[Fillable(['booking_id', 'starts_at', 'ends_at', 'expires_at'])]
class AllocationOccupancy extends Model
{
    /** @use HasFactory<AllocationOccupancyFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsToMany<AllocationUnit, $this>
     */
    public function allocationUnits(): BelongsToMany
    {
        return $this->belongsToMany(AllocationUnit::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
