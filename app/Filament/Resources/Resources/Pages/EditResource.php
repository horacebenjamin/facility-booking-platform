<?php

namespace App\Filament\Resources\Resources\Pages;

use App\Filament\Resources\Resources\ResourceResource;
use App\Models\Facility;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditResource extends EditRecord
{
    protected static string $resource = ResourceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $facility = Facility::query()->with('centre')->findOrFail(ResourceResource::validatedId($data['facility_id'] ?? null));
        abort_unless(ResourceResource::canEdit($this->getRecord())
            && ResourceResource::canConfigureCentre($facility->centre), 403);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
