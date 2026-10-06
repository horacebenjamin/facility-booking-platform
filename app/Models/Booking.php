<?php

namespace App\Models;

use App\Enums\AttendanceState;
use App\Enums\BillingMethod;
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
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int|null $booking_series_id
 * @property int|null $occurrence_index
 * @property string $reference
 * @property int $customer_id
 * @property int|null $organisation_id
 * @property int $centre_id
 * @property int $facility_id
 * @property int $resource_id
 * @property CarbonInterface $starts_at
 * @property CarbonInterface $ends_at
 * @property BookingStatus $status
 * @property AttendanceState $attendance_state
 * @property CarbonInterface|null $arrived_at
 * @property CarbonInterface|null $no_show_recorded_at
 * @property CarbonInterface|null $completed_at
 * @property FinancialStatus $financial_status
 * @property CarbonInterface|null $payment_due_at
 * @property BillingMethod $billing_method
 * @property int|null $invoice_term_days
 * @property-read Organisation|null $organisation
 */
#[Fillable(['booking_series_id', 'occurrence_index', 'reference', 'customer_id', 'organisation_id', 'centre_id', 'facility_id', 'resource_id', 'starts_at', 'ends_at', 'status', 'financial_status', 'payment_due_at', 'billing_method', 'invoice_term_days'])]
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
     * @return BelongsTo<BookingSeries, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(BookingSeries::class, 'booking_series_id');
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

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
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

    /** @return HasMany<Incident, $this> */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /** @return HasMany<DamageReport, $this> */
    public function damageReports(): HasMany
    {
        return $this->hasMany(DamageReport::class);
    }

    /**
     * @return MorphMany<Activity, $this>
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'occurrence_index' => 'integer',
            'status' => BookingStatus::class,
            'attendance_state' => AttendanceState::class,
            'arrived_at' => 'datetime',
            'no_show_recorded_at' => 'datetime',
            'completed_at' => 'datetime',
            'financial_status' => FinancialStatus::class,
            'payment_due_at' => 'datetime',
            'billing_method' => BillingMethod::class,
            'invoice_term_days' => 'integer',
        ];
    }
}
