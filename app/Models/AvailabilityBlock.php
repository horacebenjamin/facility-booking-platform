<?php

namespace App\Models;

use App\Enums\AvailabilityBlockScope;
use App\Enums\AvailabilityBlockType;
use Carbon\CarbonImmutable;
use Database\Factories\AvailabilityBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $centre_id
 * @property int|null $facility_id
 * @property int|null $resource_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string|null $reason
 * @property AvailabilityBlockType $type
 * @property int|null $created_by
 * @property Carbon|null $ended_at
 * @property int|null $ended_by
 */
#[Fillable(['centre_id', 'facility_id', 'resource_id', 'starts_at', 'ends_at', 'reason', 'type'])]
class AvailabilityBlock extends Model
{
    /** @use HasFactory<AvailabilityBlockFactory> */
    use HasFactory;

    /** @return BelongsTo<Centre, $this> */
    public function centre(): BelongsTo
    {
        return $this->belongsTo(Centre::class);
    }

    /** @return BelongsTo<Facility, $this> */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /** @return BelongsTo<\App\Models\Resource, $this> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    /** @return HasMany<AvailabilityBlockBookingImpact, $this> */
    public function impacts(): HasMany
    {
        return $this->hasMany(AvailabilityBlockBookingImpact::class);
    }

    public function owningCentre(): Centre
    {
        return match ($this->blockScope()) {
            AvailabilityBlockScope::Centre => $this->centre,
            AvailabilityBlockScope::Facility => $this->facility->centre,
            AvailabilityBlockScope::Resource => $this->resource->facility->centre,
        };
    }

    public function blockScope(): AvailabilityBlockScope
    {
        return match (true) {
            $this->centre_id !== null => AvailabilityBlockScope::Centre,
            $this->facility_id !== null => AvailabilityBlockScope::Facility,
            default => AvailabilityBlockScope::Resource,
        };
    }

    public function effectiveEndsAt(): CarbonImmutable
    {
        $end = CarbonImmutable::instance($this->ends_at);

        return $this->ended_at === null ? $end : $end->min($this->ended_at);
    }

    /**
     * @param  Builder<AvailabilityBlock>  $query
     * @param  list<int>  $centreIds
     */
    #[Scope]
    protected function scopedToCentres(Builder $query, array $centreIds): void
    {
        $query->where(function (Builder $query) use ($centreIds): void {
            $query->whereIn('centre_id', $centreIds)
                ->orWhereHas('facility', fn (Builder $query) => $query->whereIn('centre_id', $centreIds))
                ->orWhereHas('resource.facility', fn (Builder $query) => $query->whereIn('centre_id', $centreIds));
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'ended_at' => 'datetime',
            'type' => AvailabilityBlockType::class,
        ];
    }
}
