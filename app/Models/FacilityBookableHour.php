<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use Database\Factories\FacilityBookableHourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $facility_id
 * @property DayOfWeek $day_of_week
 * @property string $opens_at
 * @property string $closes_at
 */
#[Fillable(['facility_id', 'day_of_week', 'opens_at', 'closes_at'])]
class FacilityBookableHour extends Model
{
    /** @use HasFactory<FacilityBookableHourFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
        ];
    }
}
