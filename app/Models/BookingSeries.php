<?php

namespace App\Models;

use App\Enums\RecurrenceFrequency;
use Carbon\CarbonInterface;
use Database\Factories\BookingSeriesFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $identifier
 * @property int $customer_id
 * @property int|null $organisation_id
 * @property int $centre_id
 * @property int $facility_id
 * @property int $resource_id
 * @property RecurrenceFrequency $recurrence_frequency
 * @property int $interval_weeks
 * @property int $occurrence_count
 * @property string $timezone
 * @property CarbonInterface $first_starts_at
 * @property CarbonInterface $first_ends_at
 * @property-read Organisation|null $organisation
 */
#[Fillable([
    'identifier',
    'customer_id',
    'organisation_id',
    'centre_id',
    'facility_id',
    'resource_id',
    'recurrence_frequency',
    'interval_weeks',
    'occurrence_count',
    'timezone',
    'first_starts_at',
    'first_ends_at',
])]
class BookingSeries extends Model
{
    /** @use HasFactory<BookingSeriesFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
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
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class)->orderBy('occurrence_index');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recurrence_frequency' => RecurrenceFrequency::class,
            'interval_weeks' => 'integer',
            'occurrence_count' => 'integer',
            'first_starts_at' => 'datetime',
            'first_ends_at' => 'datetime',
        ];
    }
}
