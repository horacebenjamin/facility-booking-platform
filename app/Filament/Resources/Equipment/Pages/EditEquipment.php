<?php

namespace App\Filament\Resources\Equipment\Pages;

use App\Filament\Resources\Equipment\EquipmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEquipment extends EditRecord
{
    protected static string $resource = EquipmentResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        abort_unless(EquipmentResource::canEdit($this->getRecord()) && EquipmentResource::canUseLocation(
            EquipmentResource::validatedId($data['centre_id'] ?? null),
            isset($data['facility_id']) ? EquipmentResource::validatedId($data['facility_id']) : null,
        ), 403);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
