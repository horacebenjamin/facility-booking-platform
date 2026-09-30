<?php

namespace App\Models;

use Database\Factories\CentreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $address_line_1
 * @property string|null $address_line_2
 * @property string $locality
 * @property string $postcode
 * @property bool $is_active
 */
#[Fillable(['name', 'slug', 'description', 'address_line_1', 'address_line_2', 'locality', 'postcode', 'is_active'])]
class Centre extends Model
{
    /** @use HasFactory<CentreFactory> */
    use HasFactory;

    /**
     * @return HasMany<Facility, $this>
     */
    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    /**
     * @return HasMany<Equipment, $this>
     */
    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    /**
     * @return HasMany<CentreOperatingHour, $this>
     */
    public function operatingHours(): HasMany
    {
        return $this->hasMany(CentreOperatingHour::class);
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
