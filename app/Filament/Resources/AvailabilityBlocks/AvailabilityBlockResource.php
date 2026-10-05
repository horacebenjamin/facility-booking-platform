<?php

namespace App\Filament\Resources\AvailabilityBlocks;

use App\Enums\AvailabilityBlockScope;
use App\Enums\AvailabilityBlockType;
use App\Enums\ClosureImpactStatus;
use App\Filament\Resources\AvailabilityBlocks\Pages\CreateAvailabilityBlock;
use App\Filament\Resources\AvailabilityBlocks\Pages\ListAvailabilityBlocks;
use App\Filament\Resources\AvailabilityBlocks\Pages\ViewAvailabilityBlock;
use App\Filament\Resources\AvailabilityBlocks\RelationManagers\ImpactsRelationManager;
use App\Models\AvailabilityBlock;
use App\Models\Centre;
use App\Models\Facility;
use App\Models\Resource as VenueResource;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AvailabilityBlockResource extends Resource
{
    protected static ?string $model = AvailabilityBlock::class;

    protected static ?string $navigationLabel = 'Closures';

    protected static ?string $modelLabel = 'closure';

    protected static ?string $pluralModelLabel = 'closures';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Closure scope')->description('This blocks new conflicting bookings and identifies existing bookings requiring management review. It does not change bookings or financial obligations.')->schema([
                Select::make('centre_id')->label('Centre')->options(fn (): array => Centre::query()->whereIn('id', self::assignedCentreIds())->orderBy('name')->pluck('name', 'id')->all())->required()->live()->afterStateUpdated(function (Set $set): void {
                    $set('facility_id', null);
                    $set('resource_id', null);
                }),
                Select::make('scope')->options(self::enumOptions(AvailabilityBlockScope::cases()))->default('centre')->required()->live()->afterStateUpdated(function (Set $set): void {
                    $set('facility_id', null);
                    $set('resource_id', null);
                }),
                Select::make('facility_id')->label('Facility')->options(fn (Get $get): array => Facility::query()->whereIn('centre_id', self::assignedCentreIds())->where('centre_id', $get('centre_id'))->orderBy('name')->pluck('name', 'id')->all())->visible(fn (Get $get): bool => in_array($get('scope'), ['facility', 'resource'], true))->required(fn (Get $get): bool => in_array($get('scope'), ['facility', 'resource'], true))->live()->afterStateUpdated(fn (Set $set) => $set('resource_id', null)),
                Select::make('resource_id')->label('Resource')->options(fn (Get $get): array => VenueResource::query()->where('facility_id', $get('facility_id'))->whereHas('facility', fn (Builder $query) => $query->where('centre_id', $get('centre_id'))->whereIn('centre_id', self::assignedCentreIds()))->orderBy('name')->pluck('name', 'id')->all())->visible(fn (Get $get): bool => $get('scope') === 'resource')->required(fn (Get $get): bool => $get('scope') === 'resource'),
            ])->columns(2),
            Section::make('Period and reason')->description('Times use '.config('app.timezone').'.')->schema([
                Select::make('type')->options(self::enumOptions(AvailabilityBlockType::cases()))->required(),
                DateTimePicker::make('starts_at')->label('Starts')->native()->timezone(config('app.timezone'))->format('Y-m-d H:i:s')->required(),
                DateTimePicker::make('ends_at')->label('Ends')->native()->timezone(config('app.timezone'))->format('Y-m-d H:i:s')->after('starts_at')->required(),
                Textarea::make('reason')->required()->maxLength(255)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('starts_at', 'desc')->columns([
            TextColumn::make('centre_context')->label('Centre')->state(fn (AvailabilityBlock $record): string => $record->owningCentre()->name),
            TextColumn::make('scope_context')->label('Scope')->state(fn (AvailabilityBlock $record): string => self::scopeLabel($record)),
            TextColumn::make('type')->formatStateUsing(fn (AvailabilityBlockType $state): string => $state->label()),
            TextColumn::make('starts_at')->dateTime('j M Y, H:i')->sortable(),
            TextColumn::make('ends_at')->dateTime('j M Y, H:i'),
            TextColumn::make('closure_state')->label('State')->state(fn (AvailabilityBlock $record): string => $record->ended_at !== null ? 'Ended' : ($record->ends_at->isPast() ? 'Period ended' : 'Blocking availability')),
            TextColumn::make('impacts_count')->label('Affected bookings'),
            TextColumn::make('unresolved_impacts_count')->label('Unresolved'),
        ])->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([Section::make('Closure')->description('The original scope and period are preserved. To change them, end this closure and create a replacement.')->schema([
            TextEntry::make('centre_context')->label('Centre')->state(fn (AvailabilityBlock $record): string => $record->owningCentre()->name),
            TextEntry::make('scope_context')->label('Scope')->state(fn (AvailabilityBlock $record): string => self::scopeLabel($record)),
            TextEntry::make('type')->formatStateUsing(fn (AvailabilityBlockType $state): string => $state->label()),
            TextEntry::make('reason'),
            TextEntry::make('closure_state')->label('State')->state(fn (AvailabilityBlock $record): string => $record->ended_at !== null ? 'Ended' : ($record->ends_at->isPast() ? 'Period ended' : 'Blocking availability')),
            TextEntry::make('starts_at')->label('Original start')->dateTime('j M Y, H:i'),
            TextEntry::make('ends_at')->label('Original end')->dateTime('j M Y, H:i'),
            TextEntry::make('effective_end')->state(fn (AvailabilityBlock $record): string => $record->effectiveEndsAt()->format('j M Y, H:i'))->label('Effective end'),
            TextEntry::make('creator.name')->label('Created by')->placeholder('Legacy block'),
            TextEntry::make('ended_at')->label('Ended at')->dateTime('j M Y, H:i')->placeholder('Not ended'),
            TextEntry::make('endedBy.name')->label('Ended by')->placeholder('Not ended'),
            TextEntry::make('unresolved_impacts_count')->label('Unresolved bookings'),
        ])->columns(2)]);
    }

    /** @return Builder<AvailabilityBlock> */
    public static function getEloquentQuery(): Builder
    {
        return AvailabilityBlock::query()->scopedToCentres(self::assignedCentreIds())
            ->with(['centre', 'facility.centre', 'resource.facility.centre', 'creator:id,name', 'endedBy:id,name'])
            ->withCount(['impacts', 'impacts as unresolved_impacts_count' => fn (Builder $query) => $query->where('status', ClosureImpactStatus::Unresolved)]);
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [ImpactsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListAvailabilityBlocks::route('/'), 'create' => CreateAvailabilityBlock::route('/create'), 'view' => ViewAvailabilityBlock::route('/{record}')];
    }

    /** @return list<int> */
    public static function assignedCentreIds(): array
    {
        return array_values(Centre::query()->whereHas('assignedUsers', fn (Builder $query) => $query->whereKey(auth()->id()))->pluck('id')->map(fn (int $id): int => $id)->all());
    }

    /**
     * @param  list<AvailabilityBlockScope>|list<AvailabilityBlockType>  $cases
     * @return array<string, string>
     */
    private static function enumOptions(array $cases): array
    {
        $options = [];
        foreach ($cases as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    private static function scopeLabel(AvailabilityBlock $record): string
    {
        return $record->blockScope()->label().' · '.match ($record->blockScope()) {
            AvailabilityBlockScope::Centre => $record->centre->name,
            AvailabilityBlockScope::Facility => $record->facility->name,
            AvailabilityBlockScope::Resource => $record->resource->facility->name.' / '.$record->resource->name,
        };
    }
}
