<?php

namespace App\Filament\Resources\Equipment\Pages;

use App\Actions\UpdateEquipment;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Models\Equipment;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditEquipment extends EditRecord
{
    protected static string $resource = EquipmentResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = auth()->user();
        assert($actor instanceof User && $record instanceof Equipment);

        try {
            return app(UpdateEquipment::class)->handle($actor, $record, $data);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors['data.'.$field] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
    }

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
