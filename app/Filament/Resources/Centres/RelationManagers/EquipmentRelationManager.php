<?php

namespace App\Filament\Resources\Centres\RelationManagers;

use App\Filament\Resources\Concerns\CanManageVenueConfiguration;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Models\Centre;
use App\Models\Equipment;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EquipmentRelationManager extends RelationManager
{
    use CanManageVenueConfiguration;

    protected static string $relationship = 'equipment';

    protected static ?string $title = 'Equipment';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('facility.name')->label('Facility')->placeholder('Centre level'),
            TextColumn::make('quantity')->numeric(),
            IconColumn::make('is_active')->label('Active')->boolean(),
        ])->headerActions([
            CreateAction::make()->schema($this->equipmentForm())
                ->mutateDataUsing(fn (array $data): array => $this->authorizeFacility($data)),
        ])->recordActions([
            EditAction::make()->schema($this->equipmentForm())
                ->mutateDataUsing(fn (array $data): array => $this->authorizeFacility($data))
                ->using(fn (EditAction $action, Equipment $record, array $data) => EquipmentResource::updateFromAction($action, $record, $data)),
            DeleteAction::make(),
        ]);
    }

    /** @return array<Component> */
    private function equipmentForm(): array
    {
        return [
            Select::make('facility_id')->relationship(
                'facility',
                'name',
                modifyQueryUsing: fn (Builder $query): Builder => $query->where('centre_id', $this->centre()->id),
            )->searchable()->preload(),
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('quantity')->numeric()->integer()->minValue(0)->required(),
            Toggle::make('is_active')->label('Active')->default(true),
            Textarea::make('description')->columnSpanFull(),
        ];
    }

    private function centre(): Centre
    {
        $owner = $this->getOwnerRecord();

        assert($owner instanceof Centre);

        return $owner;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function authorizeFacility(array $data): array
    {
        abort_unless(EquipmentResource::canUseLocation(
            $this->centre()->id,
            isset($data['facility_id']) ? EquipmentResource::validatedId($data['facility_id']) : null,
        ), 403);

        return $data;
    }
}
