<?php

namespace App\Filament\Resources\Centres\Pages;

use App\Filament\Resources\Centres\CentreResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCentre extends EditRecord
{
    protected static string $resource = CentreResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
