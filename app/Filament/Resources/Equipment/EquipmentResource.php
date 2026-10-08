<?php

namespace App\Filament\Resources\Equipment;

use App\Actions\UpdateEquipment;
use App\Filament\Resources\Equipment\Pages\CreateEquipment;
use App\Filament\Resources\Equipment\Pages\EditEquipment;
use App\Filament\Resources\Equipment\Pages\ListEquipment;
use App\Filament\Resources\VenueConfigurationResource;
use App\Models\Centre;
use App\Models\Equipment;
use App\Models\Facility;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class EquipmentResource extends VenueConfigurationResource
{
    protected static ?string $model = Equipment::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function canUseLocation(int $centreId, ?int $facilityId): bool
    {
        $centre = Centre::query()->find($centreId);

        return $centre !== null
            && static::canConfigureCentre($centre)
            && ($facilityId === null || Facility::query()->whereKey($facilityId)->where('centre_id', $centreId)->exists());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Equipment details')->schema([
                Select::make('centre_id')->relationship('centre', 'name',
                    modifyQueryUsing: fn (Builder $query): Builder => $query->whereHas('assignedUsers', fn (Builder $assigned): Builder => $assigned->whereKey(auth()->id())),
                )->required()->searchable()->preload()->live()
                    ->afterStateUpdated(fn (Set $set) => $set('facility_id', null)),
                Select::make('facility_id')->relationship(
                    'facility',
                    'name',
                    modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query->where('centre_id', $get('centre_id')),
                )->searchable()->preload(),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('quantity')->numeric()->integer()->minValue(0)->required(),
                Textarea::make('description')->columnSpanFull(),
                Toggle::make('is_active')->label('Active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('centre.name')->label('Centre')->searchable()->sortable(),
            TextColumn::make('facility.name')->label('Facility')->placeholder('Centre level'),
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('quantity')->numeric(),
            IconColumn::make('is_active')->label('Active')->boolean(),
        ])->filters([
            SelectFilter::make('centre')->relationship('centre', 'name',
                modifyQueryUsing: fn (Builder $query): Builder => $query->whereHas('assignedUsers', fn (Builder $assigned): Builder => $assigned->whereKey(auth()->id())),
            ),
        ])->recordActions([
            EditAction::make()->using(fn (EditAction $action, Equipment $record, array $data) => static::updateFromAction($action, $record, $data)),
            DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEquipment::route('/'),
            'create' => CreateEquipment::route('/create'),
            'edit' => EditEquipment::route('/{record}/edit'),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function updateFromAction(EditAction $action, Equipment $record, array $data): void
    {
        $actor = auth()->user();
        assert($actor instanceof User);
        try {
            app(UpdateEquipment::class)->handle($actor, $record, $data);
        } catch (ValidationException $exception) {
            Notification::make()->title('Equipment could not be updated')
                ->body((string) Arr::first(Arr::flatten($exception->errors())))->danger()->send();
            $action->halt();
        }
    }
}
