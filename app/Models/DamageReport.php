<?php

namespace App\Models;

use App\Enums\DamageFinancialFollowUp;
use App\Enums\DamageResponsibility;
use App\Enums\OperationalIssueStatus;
use Carbon\CarbonInterface;
use Database\Factories\DamageReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $centre_id
 * @property int|null $booking_id
 * @property int|null $resource_id
 * @property int|null $equipment_id
 * @property int $reported_by
 * @property int|null $reviewed_by
 * @property int|null $resolved_by
 * @property int|null $closed_by
 * @property string $description
 * @property CarbonInterface $observed_at
 * @property OperationalIssueStatus $status
 * @property DamageResponsibility $responsibility
 * @property DamageFinancialFollowUp $financial_follow_up
 * @property string|null $follow_up_notes
 */
#[Fillable(['centre_id', 'booking_id', 'resource_id', 'equipment_id', 'description', 'observed_at', 'status', 'responsibility', 'financial_follow_up', 'follow_up_notes'])]
class DamageReport extends Model
{
    /** @use HasFactory<DamageReportFactory> */
    use HasFactory;

    /** @return BelongsTo<Centre, $this> */
    public function centre(): BelongsTo
    {
        return $this->belongsTo(Centre::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<\App\Models\Resource, $this> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'status' => OperationalIssueStatus::class,
            'responsibility' => DamageResponsibility::class,
            'financial_follow_up' => DamageFinancialFollowUp::class,
        ];
    }
}
