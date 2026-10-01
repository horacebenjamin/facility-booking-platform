<?php

namespace App\Filament\Resources\ResourceRates;

use App\Enums\PricingRateUnit;
use App\Filament\Resources\ResourceRates\Pages\CreateResourceRate;
use App\Filament\Resources\ResourceRates\Pages\EditResourceRate;
use App\Filament\Resources\ResourceRates\Pages\ListResourceRates;
use App\Models\Resource;
use App\Models\ResourceRate;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ResourceRateResource extends \Filament\Resources\Resource
{
    protected static ?string $model = ResourceRate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Pricing';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Resource rate')->schema([
                Select::make('resource_id')
                    ->relationship('resource', 'name', modifyQueryUsing: fn (Builder $query): Builder => self::scopeResourcesToAssignedCentres($query))
                    ->getOptionLabelFromRecordUsing(fn (Resource $record): string => "{$record->facility->centre->name} — {$record->name}")
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('amount_minor')
                    ->label('Amount (minor units)')
                    ->helperText('Store GBP pence as an integer, for example 2500 for £25.00.')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->required(),
                TextInput::make('currency')
                    ->default('GBP')
                    ->minLength(3)
                    ->maxLength(3)
                    ->regex('/^[A-Z]{3}$/')
                    ->required(),
                Select::make('rate_unit')
                    ->options(self::rateUnitOptions())
                    ->default(PricingRateUnit::Hourly->value)
                    ->required(),
                DatePicker::make('effective_from')->required(),
                DatePicker::make('effective_until')->afterOrEqual('effective_from'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('resource.facility.centre.name')->label('Centre')->sortable(),
            TextColumn::make('resource.facility.name')->label('Facility')->sortable(),
            TextColumn::make('resource.name')->label('Resource')->searchable()->sortable(),
            TextColumn::make('amount_minor')->label('Amount (minor units)')->numeric(),
            TextColumn::make('currency'),
            TextColumn::make('rate_unit')->formatStateUsing(fn (PricingRateUnit $state): string => $state->label()),
            TextColumn::make('effective_from')->date('j M Y')->sortable(),
            TextColumn::make('effective_until')->date('j M Y')->placeholder('Ongoing'),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('resource.facility.centre.assignedUsers', fn (Builder $query): Builder => $query->whereKey(auth()->id()));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListResourceRates::route('/'),
            'create' => CreateResourceRate::route('/create'),
            'edit' => EditResourceRate::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function rateUnitOptions(): array
    {
        return collect(PricingRateUnit::cases())
            ->mapWithKeys(fn (PricingRateUnit $rateUnit): array => [$rateUnit->value => $rateUnit->label()])
            ->all();
    }

    /**
     * @param  Builder<resource>  $query
     * @return Builder<resource>
     */
    private static function scopeResourcesToAssignedCentres(Builder $query): Builder
    {
        return $query->whereHas('facility.centre.assignedUsers', fn (Builder $centreQuery): Builder => $centreQuery->whereKey(auth()->id()));
    }
}
