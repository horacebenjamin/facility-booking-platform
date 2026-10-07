<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Carbon\CarbonInterface;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property string $reference
 * @property int $customer_id
 * @property int|null $organisation_id
 * @property int $issued_by
 * @property CarbonInterface $issue_date
 * @property CarbonInterface $due_date
 * @property InvoiceStatus $status
 * @property string $currency
 * @property int $total_minor
 * @property CarbonInterface|null $paid_at
 * @property-read Organisation|null $organisation
 */
#[Fillable(['reference', 'customer_id', 'organisation_id', 'issued_by', 'issue_date', 'due_date', 'status', 'currency', 'total_minor', 'paid_at'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return MorphMany<Activity, $this> */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function isOverdue(?Carbon $at = null): bool
    {
        return $this->status !== InvoiceStatus::Paid
            && $this->due_date->endOfDay()->lt($at ?? Carbon::now(config('app.timezone')));
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    #[Scope]
    protected function overdue(Builder $query, ?Carbon $at = null): void
    {
        $query
            ->where('status', '!=', InvoiceStatus::Paid)
            ->where('due_date', '<', ($at ?? Carbon::now(config('app.timezone')))->toDateString());
    }

    /**
     * @param  Builder<Invoice>  $query
     * @param  list<int>  $centreIds
     */
    #[Scope]
    protected function scopedToCentres(Builder $query, array $centreIds): void
    {
        $query
            ->whereHas('lines.booking')
            ->whereDoesntHave('lines.booking', fn (Builder $bookings): Builder => $bookings->whereNotIn('centre_id', $centreIds));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['issue_date' => 'date', 'due_date' => 'date', 'status' => InvoiceStatus::class, 'total_minor' => 'integer', 'paid_at' => 'datetime'];
    }
}
