<?php

namespace App\Filament\Resources\EquipmentRates;

use App\Enums\EquipmentChargeType;
use App\Enums\PricingRateUnit;
use App\Filament\Resources\EquipmentRates\Pages\CreateEquipmentRate;
use App\Filament\Resources\EquipmentRates\Pages\EditEquipmentRate;
use App\Filament\Resources\EquipmentRates\Pages\ListEquipmentRates;
use App\Models\Equipment;
use App\Models\EquipmentRate;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class EquipmentRateResource extends \Filament\Resources\Resource
{
    protected static ?string $model = EquipmentRate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string|UnitEnum|null $navigationGroup = 'Pricing';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Equipment rate')->schema([
                Select::make('equipment_id')
                    ->relationship('equipment', 'name', modifyQueryUsing: fn (Builder $query): Builder => self::scopeEquipmentToAssignedCentres($query))
                    ->getOptionLabelFromRecordUsing(fn (Equipment $record): string => "{$record->centre->name} — {$record->name}")
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('charge_type')
                    ->options(self::chargeTypeOptions())
                    ->default(EquipmentChargeType::SeparatelyChargeable->value)
                    ->live()
                    ->required(),
                TextInput::make('amount_minor')
                    ->label('Amount (minor units)')
                    ->helperText('Included equipment must use 0. Separately chargeable equipment uses GBP pence, for example 500 for £5.00.')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->rule(function (Get $get): \Closure {
                        return function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                            if ($get('charge_type') === EquipmentChargeType::Included->value && (int) $value !== 0) {
                                $fail('Included equipment must have an amount of 0 minor units.');
                            }

                            if ($get('charge_type') === EquipmentChargeType::SeparatelyChargeable->value && (int) $value < 1) {
                                $fail('Separately chargeable equipment must have an amount greater than 0 minor units.');
                            }
                        };
                    })
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
            TextColumn::make('equipment.centre.name')->label('Centre')->sortable(),
            TextColumn::make('equipment.facility.name')->label('Facility')->placeholder('Centre level'),
            TextColumn::make('equipment.name')->label('Equipment')->searchable()->sortable(),
            TextColumn::make('charge_type')->formatStateUsing(fn (EquipmentChargeType $state): string => $state->label()),
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
            ->whereHas('equipment.centre.assignedUsers', fn (Builder $query): Builder => $query->whereKey(auth()->id()));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEquipmentRates::route('/'),
            'create' => CreateEquipmentRate::route('/create'),
            'edit' => EditEquipmentRate::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function chargeTypeOptions(): array
    {
        return collect(EquipmentChargeType::cases())
            ->mapWithKeys(fn (EquipmentChargeType $chargeType): array => [$chargeType->value => $chargeType->label()])
            ->all();
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
     * @param  Builder<Equipment>  $query
     * @return Builder<Equipment>
     */
    private static function scopeEquipmentToAssignedCentres(Builder $query): Builder
    {
        return $query->whereHas('centre.assignedUsers', fn (Builder $centreQuery): Builder => $centreQuery->whereKey(auth()->id()));
    }
}
