<?php

namespace App\Filament\Resources\Centres\Pages;

use App\Filament\Resources\Centres\CentreResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCentre extends EditRecord
{
    protected static string $resource = CentreResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        abort_unless(CentreResource::canEdit($this->getRecord()), 403);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
