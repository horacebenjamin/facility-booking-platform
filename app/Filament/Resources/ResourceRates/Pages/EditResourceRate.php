<?php

namespace App\Filament\Resources\ResourceRates\Pages;

use App\Filament\Resources\ResourceRates\ResourceRateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditResourceRate extends EditRecord
{
    protected static string $resource = ResourceRateResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
