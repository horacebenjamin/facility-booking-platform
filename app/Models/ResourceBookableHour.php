<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use Database\Factories\ResourceBookableHourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $resource_id
 * @property DayOfWeek $day_of_week
 * @property string $opens_at
 * @property string $closes_at
 */
#[Fillable(['resource_id', 'day_of_week', 'opens_at', 'closes_at'])]
class ResourceBookableHour extends Model
{
    /** @use HasFactory<ResourceBookableHourFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<\App\Models\Resource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
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
