<?php

namespace App\Models;

use Database\Factories\EquipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $centre_id
 * @property int|null $facility_id
 * @property string $name
 * @property string|null $description
 * @property int $quantity
 * @property bool $is_active
 */
#[Fillable(['centre_id', 'facility_id', 'name', 'description', 'quantity', 'is_active'])]
class Equipment extends Model
{
    /** @use HasFactory<EquipmentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Centre, $this>
     */
    public function centre(): BelongsTo
    {
        return $this->belongsTo(Centre::class);
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return HasMany<EquipmentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(EquipmentAllocation::class);
    }

    /**
     * @return HasMany<EquipmentRate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(EquipmentRate::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
