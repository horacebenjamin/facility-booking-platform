<?php

namespace App\Filament\Resources\EquipmentRates\Pages;

use App\Filament\Resources\EquipmentRates\EquipmentRateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEquipmentRates extends ListRecords
{
    protected static string $resource = EquipmentRateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
