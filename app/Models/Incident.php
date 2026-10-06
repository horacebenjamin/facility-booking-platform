<?php

namespace App\Models;

use App\Enums\OperationalIssueStatus;
use Carbon\CarbonInterface;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $centre_id
 * @property int|null $booking_id
 * @property int|null $resource_id
 * @property int $reported_by
 * @property int|null $reviewed_by
 * @property int|null $resolved_by
 * @property int|null $closed_by
 * @property string $issue_type
 * @property string $title
 * @property string $description
 * @property string|null $immediate_action
 * @property CarbonInterface $occurred_at
 * @property OperationalIssueStatus $status
 * @property string|null $follow_up_notes
 * @property CarbonInterface|null $reviewed_at
 * @property CarbonInterface|null $resolved_at
 * @property CarbonInterface|null $closed_at
 */
#[Fillable(['centre_id', 'booking_id', 'resource_id', 'issue_type', 'title', 'description', 'immediate_action', 'occurred_at', 'status', 'follow_up_notes'])]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
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
            'occurred_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'status' => OperationalIssueStatus::class,
        ];
    }
}
