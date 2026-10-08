<?php

namespace App\Filament\Resources\Resources;

use App\Filament\Resources\Resources\Pages\CreateResource;
use App\Filament\Resources\Resources\Pages\EditResource;
use App\Filament\Resources\Resources\Pages\ListResources;
use App\Filament\Resources\Resources\RelationManagers\AllocationUnitsRelationManager;
use App\Filament\Resources\Resources\RelationManagers\BookableHoursRelationManager;
use App\Filament\Resources\VenueConfigurationResource;
use App\Models\Resource;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class ResourceResource extends VenueConfigurationResource
{
    protected static ?string $model = Resource::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Resource details')->schema([
                Select::make('facility_id')->relationship('facility', 'name',
                    modifyQueryUsing: fn (Builder $query): Builder => $query->whereHas('centre.assignedUsers', fn (Builder $assigned): Builder => $assigned->whereKey(auth()->id())),
                )->required()->searchable()->preload(),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('slug')->required()->maxLength(255)->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('facility_id', $get('facility_id')),
                ),
                TextInput::make('capacity')->numeric()->minValue(1),
                TextInput::make('setup_minutes')->label('Setup minutes')->numeric()->integer()->minValue(0)->default(0)->required(),
                TextInput::make('cleanup_minutes')->label('Cleanup minutes')->numeric()->integer()->minValue(0)->default(0)->required(),
                Textarea::make('description')->columnSpanFull(),
                Toggle::make('is_active')->label('Active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('facility.centre.name')->label('Centre')->sortable(),
            TextColumn::make('facility.name')->label('Facility')->searchable()->sortable(),
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('setup_minutes')->label('Setup')->suffix(' min'),
            TextColumn::make('cleanup_minutes')->label('Cleanup')->suffix(' min'),
            IconColumn::make('is_active')->label('Active')->boolean(),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getRelations(): array
    {
        return [AllocationUnitsRelationManager::class, BookableHoursRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListResources::route('/'),
            'create' => CreateResource::route('/create'),
            'edit' => EditResource::route('/{record}/edit'),
        ];
    }
}
