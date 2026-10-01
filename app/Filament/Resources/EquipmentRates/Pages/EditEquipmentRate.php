<?php

namespace App\Filament\Resources\EquipmentRates\Pages;

use App\Filament\Resources\EquipmentRates\EquipmentRateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEquipmentRate extends EditRecord
{
    protected static string $resource = EquipmentRateResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
