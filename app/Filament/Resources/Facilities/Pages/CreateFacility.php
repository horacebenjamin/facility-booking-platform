<?php

namespace App\Filament\Resources\Facilities\Pages;

use App\Filament\Resources\Facilities\FacilityResource;
use App\Models\Centre;
use Filament\Resources\Pages\CreateRecord;

class CreateFacility extends CreateRecord
{
    protected static string $resource = FacilityResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        abort_unless(FacilityResource::canCreate()
            && FacilityResource::canConfigureCentre(Centre::query()->findOrFail(FacilityResource::validatedId($data['centre_id'] ?? null))), 403);

        return $data;
    }
}
