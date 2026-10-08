<?php

namespace App\Filament\Resources\Facilities;

use App\Filament\Resources\Facilities\Pages\CreateFacility;
use App\Filament\Resources\Facilities\Pages\EditFacility;
use App\Filament\Resources\Facilities\Pages\ListFacilities;
use App\Filament\Resources\Facilities\RelationManagers\AllocationUnitsRelationManager;
use App\Filament\Resources\Facilities\RelationManagers\BookableHoursRelationManager;
use App\Filament\Resources\Facilities\RelationManagers\ResourcesRelationManager;
use App\Filament\Resources\VenueConfigurationResource;
use App\Models\Facility;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class FacilityResource extends VenueConfigurationResource
{
    protected static ?string $model = Facility::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Facility details')->schema([
                Select::make('centre_id')->relationship('centre', 'name',
                    modifyQueryUsing: fn (Builder $query): Builder => $query->whereHas('assignedUsers', fn (Builder $assigned): Builder => $assigned->whereKey(auth()->id())),
                )->required()->searchable()->preload(),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('slug')->required()->maxLength(255)->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('centre_id', $get('centre_id')),
                ),
                TextInput::make('capacity')->numeric()->minValue(1),
                Textarea::make('description')->columnSpanFull(),
                Toggle::make('is_active')->label('Active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('centre.name')->label('Centre')->searchable()->sortable(),
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('capacity')->numeric(),
            IconColumn::make('is_active')->label('Active')->boolean(),
        ])->filters([
            SelectFilter::make('centre')->relationship('centre', 'name',
                modifyQueryUsing: fn (Builder $query): Builder => $query->whereHas('assignedUsers', fn (Builder $assigned): Builder => $assigned->whereKey(auth()->id())),
            ),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getRelations(): array
    {
        return [ResourcesRelationManager::class, AllocationUnitsRelationManager::class, BookableHoursRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFacilities::route('/'),
            'create' => CreateFacility::route('/create'),
            'edit' => EditFacility::route('/{record}/edit'),
        ];
    }
}
