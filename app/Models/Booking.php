<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\FinancialStatus;
use Carbon\CarbonInterface;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $reference
 * @property int $customer_id
 * @property int $centre_id
 * @property int $facility_id
 * @property int $resource_id
 * @property CarbonInterface $starts_at
 * @property CarbonInterface $ends_at
 * @property BookingStatus $status
 * @property FinancialStatus $financial_status
 */
#[Fillable(['reference', 'customer_id', 'centre_id', 'facility_id', 'resource_id', 'starts_at', 'ends_at', 'status', 'financial_status'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<Centre, $this>
     */
    public function centre(): BelongsTo
    {
        return $this->belongsTo(Centre::class);
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return BelongsTo<\App\Models\Resource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /**
     * @return HasMany<BookingEquipment, $this>
     */
    public function equipmentRequests(): HasMany
    {
        return $this->hasMany(BookingEquipment::class);
    }

    /**
     * @return HasOne<BookingPriceSnapshot, $this>
     */
    public function priceSnapshot(): HasOne
    {
        return $this->hasOne(BookingPriceSnapshot::class);
    }

    /**
     * @return HasOne<AllocationOccupancy, $this>
     */
    public function allocationOccupancy(): HasOne
    {
        return $this->hasOne(AllocationOccupancy::class);
    }

    /**
     * @return HasMany<EquipmentAllocation, $this>
     */
    public function equipmentAllocations(): HasMany
    {
        return $this->hasMany(EquipmentAllocation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => BookingStatus::class,
            'financial_status' => FinancialStatus::class,
        ];
    }
}
