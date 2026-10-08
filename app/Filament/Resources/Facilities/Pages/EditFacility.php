<?php

namespace App\Filament\Resources\Facilities\Pages;

use App\Filament\Resources\Facilities\FacilityResource;
use App\Models\Centre;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFacility extends EditRecord
{
    protected static string $resource = FacilityResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        abort_unless(FacilityResource::canEdit($this->getRecord())
            && FacilityResource::canConfigureCentre(Centre::query()->findOrFail(FacilityResource::validatedId($data['centre_id'] ?? null))), 403);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
