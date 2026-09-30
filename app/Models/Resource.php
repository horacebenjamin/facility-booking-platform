<?php

namespace App\Models;

use Database\Factories\ResourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

/**
 * @property int $id
 * @property int $facility_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int|null $capacity
 * @property bool $is_active
 */
#[Fillable(['facility_id', 'name', 'slug', 'description', 'capacity', 'is_active'])]
class Resource extends Model
{
    /** @use HasFactory<ResourceFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return BelongsToMany<AllocationUnit, $this>
     */
    public function allocationUnits(): BelongsToMany
    {
        return $this->belongsToMany(AllocationUnit::class)->withPivot('facility_id');
    }

    public function syncAllocationUnits(AllocationUnit ...$allocationUnits): void
    {
        foreach ($allocationUnits as $allocationUnit) {
            if ($allocationUnit->facility_id !== $this->facility_id) {
                throw new InvalidArgumentException('An allocation unit must belong to the resource facility.');
            }
        }

        $this->allocationUnits()->syncWithPivotValues($allocationUnits, [
            'facility_id' => $this->facility_id,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
