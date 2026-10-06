<?php

namespace App\Filament\Resources\DamageReports;

use App\Enums\DamageFinancialFollowUp;
use App\Enums\DamageResponsibility;
use App\Enums\OperationalIssueStatus;
use App\Filament\Resources\DamageReports\Pages\ListDamageReports;
use App\Filament\Resources\DamageReports\Pages\ViewDamageReport;
use App\Models\Centre;
use App\Models\DamageReport;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DamageReportResource extends Resource
{
    protected static ?string $model = DamageReport::class;

    protected static ?string $navigationLabel = 'Damage reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasRole('manager') && $user->can('incidents.manage');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Damage report')->schema([
                TextEntry::make('status')->badge()->formatStateUsing(fn (OperationalIssueStatus $state): string => $state->label()),
                TextEntry::make('observed_at')->label('Observed at')->dateTime('j M Y, H:i'),
                TextEntry::make('description')->columnSpanFull(),
            ])->columns(2),
            Section::make('Context')->schema([
                TextEntry::make('centre.name')->label('Centre'),
                TextEntry::make('booking.reference')->label('Booking')->placeholder('Not linked'),
                TextEntry::make('resource.name')->label('Resource')->placeholder('Not linked'),
                TextEntry::make('equipment.name')->label('Equipment')->placeholder('Not linked'),
                TextEntry::make('reporter.name')->label('Reported by'),
            ])->columns(2),
            Section::make('Management follow-up')->schema([
                TextEntry::make('responsibility')->formatStateUsing(fn (DamageResponsibility $state): string => $state->label()),
                TextEntry::make('financial_follow_up')->label('Financial follow-up')->formatStateUsing(fn (DamageFinancialFollowUp $state): string => $state->label()),
                TextEntry::make('follow_up_notes')->label('Follow-up notes')->placeholder('No follow-up recorded')->columnSpanFull(),
                TextEntry::make('reviewer.name')->label('Reviewed by')->placeholder('Not reviewed'),
                TextEntry::make('reviewed_at')->label('Reviewed at')->dateTime('j M Y, H:i')->placeholder('Not reviewed'),
                TextEntry::make('resolver.name')->label('Resolved by')->placeholder('Not resolved'),
                TextEntry::make('resolved_at')->label('Resolved at')->dateTime('j M Y, H:i')->placeholder('Not resolved'),
                TextEntry::make('closer.name')->label('Closed by')->placeholder('Not closed'),
                TextEntry::make('closed_at')->label('Closed at')->dateTime('j M Y, H:i')->placeholder('Not closed'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('observed_at', 'desc')->columns([
            TextColumn::make('status')->badge()->formatStateUsing(fn (OperationalIssueStatus $state): string => $state->label()),
            TextColumn::make('centre.name')->label('Centre')->sortable(),
            TextColumn::make('booking.reference')->label('Booking')->placeholder('Not linked'),
            TextColumn::make('resource.name')->label('Resource')->placeholder('Not linked'),
            TextColumn::make('equipment.name')->label('Equipment')->placeholder('Not linked'),
            TextColumn::make('observed_at')->dateTime('j M Y, H:i')->sortable(),
            TextColumn::make('responsibility')->formatStateUsing(fn (DamageResponsibility $state): string => $state->label()),
            TextColumn::make('reporter.name')->label('Reported by'),
        ])->recordActions([ViewAction::make()]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDamageReports::route('/'),
            'view' => ViewDamageReport::route('/{record}'),
        ];
    }

    /** @return Builder<DamageReport> */
    public static function getEloquentQuery(): Builder
    {
        return DamageReport::query()->whereIn('centre_id', self::assignedCentreIds())
            ->with(['centre', 'booking', 'resource', 'equipment', 'reporter', 'reviewer', 'resolver', 'closer']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /** @return list<int> */
    public static function assignedCentreIds(): array
    {
        return array_values(Centre::query()->whereHas('assignedUsers', fn (Builder $query) => $query->whereKey(auth()->id()))->pluck('id')->map(fn (int $id): int => $id)->all());
    }
}
