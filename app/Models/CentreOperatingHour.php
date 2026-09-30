<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use Database\Factories\CentreOperatingHourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $centre_id
 * @property DayOfWeek $day_of_week
 * @property string $opens_at
 * @property string $closes_at
 */
#[Fillable(['centre_id', 'day_of_week', 'opens_at', 'closes_at'])]
class CentreOperatingHour extends Model
{
    /** @use HasFactory<CentreOperatingHourFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Centre, $this>
     */
    public function centre(): BelongsTo
    {
        return $this->belongsTo(Centre::class);
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
