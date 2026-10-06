<?php

namespace App\Filament\Resources\Incidents;

use App\Enums\OperationalIssueStatus;
use App\Filament\Resources\Incidents\Pages\ListIncidents;
use App\Filament\Resources\Incidents\Pages\ViewIncident;
use App\Models\Centre;
use App\Models\Incident;
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

class IncidentResource extends Resource
{
    protected static ?string $model = Incident::class;

    protected static ?string $navigationLabel = 'Incidents';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

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
            Section::make('Incident')->schema([
                TextEntry::make('issue_type')->label('Issue type'),
                TextEntry::make('title'),
                TextEntry::make('status')->badge()->formatStateUsing(fn (OperationalIssueStatus $state): string => $state->label()),
                TextEntry::make('occurred_at')->label('Occurred at')->dateTime('j M Y, H:i'),
                TextEntry::make('description')->columnSpanFull(),
                TextEntry::make('immediate_action')->label('Immediate action')->placeholder('None recorded')->columnSpanFull(),
            ])->columns(2),
            Section::make('Context')->schema([
                TextEntry::make('centre.name')->label('Centre'),
                TextEntry::make('booking.reference')->label('Booking')->placeholder('Not linked'),
                TextEntry::make('resource.name')->label('Resource')->placeholder('Not linked'),
                TextEntry::make('reporter.name')->label('Reported by'),
            ])->columns(2),
            Section::make('Management follow-up')->schema([
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
        return $table->defaultSort('occurred_at', 'desc')->columns([
            TextColumn::make('status')->badge()->formatStateUsing(fn (OperationalIssueStatus $state): string => $state->label()),
            TextColumn::make('issue_type')->label('Type')->searchable(),
            TextColumn::make('title')->searchable()->wrap(),
            TextColumn::make('centre.name')->label('Centre')->sortable(),
            TextColumn::make('booking.reference')->label('Booking')->placeholder('Not linked'),
            TextColumn::make('resource.name')->label('Resource')->placeholder('Not linked'),
            TextColumn::make('occurred_at')->dateTime('j M Y, H:i')->sortable(),
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
            'index' => ListIncidents::route('/'),
            'view' => ViewIncident::route('/{record}'),
        ];
    }

    /** @return Builder<Incident> */
    public static function getEloquentQuery(): Builder
    {
        return Incident::query()->whereIn('centre_id', self::assignedCentreIds())
            ->with(['centre', 'booking', 'resource', 'reporter', 'reviewer', 'resolver', 'closer']);
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
