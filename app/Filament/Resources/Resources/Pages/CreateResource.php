<?php

namespace App\Filament\Resources\Resources\Pages;

use App\Filament\Resources\Resources\ResourceResource;
use App\Models\Facility;
use Filament\Resources\Pages\CreateRecord;

class CreateResource extends CreateRecord
{
    protected static string $resource = ResourceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $facility = Facility::query()->with('centre')->findOrFail(ResourceResource::validatedId($data['facility_id'] ?? null));
        abort_unless(ResourceResource::canCreate() && ResourceResource::canConfigureCentre($facility->centre), 403);

        return $data;
    }
}
