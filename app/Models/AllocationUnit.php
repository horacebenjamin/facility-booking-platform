<?php

namespace App\Models;

use Database\Factories\AllocationUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $facility_id
 * @property string $name
 * @property string $code
 * @property bool $is_active
 */
#[Fillable(['facility_id', 'name', 'code', 'is_active'])]
class AllocationUnit extends Model
{
    /** @use HasFactory<AllocationUnitFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return BelongsToMany<\App\Models\Resource, $this>
     */
    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class)->withPivot('facility_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
