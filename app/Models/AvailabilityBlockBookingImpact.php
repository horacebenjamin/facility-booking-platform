<?php

namespace App\Models;

use App\Enums\ClosureFinancialRequirement;
use App\Enums\ClosureImpactStatus;
use App\Enums\ClosureOperationalRequirement;
use Database\Factories\AvailabilityBlockBookingImpactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $availability_block_id
 * @property int $booking_id
 * @property Carbon $detected_at
 * @property ClosureImpactStatus $status
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property ClosureOperationalRequirement $operational_requirement
 * @property ClosureFinancialRequirement $financial_requirement
 * @property bool $communication_required
 * @property Carbon|null $communicated_at
 * @property int|null $communicated_by
 * @property string|null $communication_notes
 * @property string|null $review_notes
 * @property string|null $resolution_notes
 */
class AvailabilityBlockBookingImpact extends Model
{
    /** @use HasFactory<AvailabilityBlockBookingImpactFactory> */
    use HasFactory;

    /** @return BelongsTo<AvailabilityBlock, $this> */
    public function availabilityBlock(): BelongsTo
    {
        return $this->belongsTo(AvailabilityBlock::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function communicatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'communicated_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'status' => ClosureImpactStatus::class,
            'resolved_at' => 'datetime',
            'operational_requirement' => ClosureOperationalRequirement::class,
            'financial_requirement' => ClosureFinancialRequirement::class,
            'communication_required' => 'boolean',
            'communicated_at' => 'datetime',
        ];
    }
}
