<?php

namespace App\Filament\Resources\Centres;

use App\Filament\Resources\Centres\Pages\CreateCentre;
use App\Filament\Resources\Centres\Pages\EditCentre;
use App\Filament\Resources\Centres\Pages\ListCentres;
use App\Filament\Resources\Centres\RelationManagers\AssignedUsersRelationManager;
use App\Filament\Resources\Centres\RelationManagers\EquipmentRelationManager;
use App\Filament\Resources\Centres\RelationManagers\FacilitiesRelationManager;
use App\Filament\Resources\Centres\RelationManagers\OperatingHoursRelationManager;
use App\Filament\Resources\VenueConfigurationResource;
use App\Models\Centre;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CentreResource extends VenueConfigurationResource
{
    protected static ?string $model = Centre::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|UnitEnum|null $navigationGroup = 'Venue configuration';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Centre details')->schema([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true),
                Textarea::make('description')->columnSpanFull(),
                Toggle::make('is_active')->label('Active')->default(true),
            ])->columns(2),
            Section::make('Address')->schema([
                TextInput::make('address_line_1')->label('Address line 1')->required()->maxLength(255),
                TextInput::make('address_line_2')->label('Address line 2')->maxLength(255),
                TextInput::make('locality')->required()->maxLength(255),
                TextInput::make('postcode')->required()->maxLength(255),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('locality')->searchable()->sortable(),
                TextColumn::make('postcode')->searchable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            FacilitiesRelationManager::class,
            EquipmentRelationManager::class,
            OperatingHoursRelationManager::class,
            AssignedUsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCentres::route('/'),
            'create' => CreateCentre::route('/create'),
            'edit' => EditCentre::route('/{record}/edit'),
        ];
    }
}
