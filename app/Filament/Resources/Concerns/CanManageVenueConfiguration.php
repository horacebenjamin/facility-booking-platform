<?php

namespace App\Filament\Resources\Concerns;

use App\Filament\Resources\VenueConfigurationResource;
use Illuminate\Database\Eloquent\Model;

trait CanManageVenueConfiguration
{
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return VenueConfigurationResource::canView($ownerRecord);
    }
}
