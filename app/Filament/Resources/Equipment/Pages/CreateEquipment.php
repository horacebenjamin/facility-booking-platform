<?php

namespace App\Filament\Resources\Equipment\Pages;

use App\Filament\Resources\Equipment\EquipmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEquipment extends CreateRecord
{
    protected static string $resource = EquipmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        abort_unless(EquipmentResource::canCreate() && EquipmentResource::canUseLocation(
            EquipmentResource::validatedId($data['centre_id'] ?? null),
            isset($data['facility_id']) ? EquipmentResource::validatedId($data['facility_id']) : null,
        ), 403);

        return $data;
    }
}
