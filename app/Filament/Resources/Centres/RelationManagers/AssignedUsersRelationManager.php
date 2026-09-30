<?php

namespace App\Filament\Resources\Centres\RelationManagers;

use App\Filament\Resources\Concerns\CanManageVenueConfiguration;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssignedUsersRelationManager extends RelationManager
{
    use CanManageVenueConfiguration;

    protected static string $relationship = 'assignedUsers';

    protected static ?string $inverseRelationship = 'assignedCentres';

    protected static ?string $title = 'Assigned staff';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('email')->searchable(),
        ])->headerActions([
            AttachAction::make()
                ->preloadRecordSelect()
                ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query->whereHas(
                    'roles',
                    fn (Builder $roleQuery): Builder => $roleQuery->whereIn('name', ['manager', 'leisure-assistant']),
                )),
        ])->recordActions([DetachAction::make()]);
    }
}
