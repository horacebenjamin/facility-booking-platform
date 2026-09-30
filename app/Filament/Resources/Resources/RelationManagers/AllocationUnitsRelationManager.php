<?php

namespace App\Filament\Resources\Resources\RelationManagers;

use App\Filament\Resources\Concerns\CanManageVenueConfiguration;
use App\Models\Resource;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Hidden;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AllocationUnitsRelationManager extends RelationManager
{
    use CanManageVenueConfiguration;

    protected static string $relationship = 'allocationUnits';

    protected static ?string $title = 'Allocation units';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('code')->searchable(),
            IconColumn::make('is_active')->label('Active')->boolean(),
        ])->headerActions([
            AttachAction::make()
                ->preloadRecordSelect()
                ->recordSelectOptionsQuery(
                    fn (Builder $query): Builder => $query->where('allocation_units.facility_id', $this->resource()->facility_id),
                )
                ->schema(fn (AttachAction $action): array => [
                    $action->getRecordSelect(),
                    Hidden::make('facility_id')->default($this->resource()->facility_id),
                ]),
        ])->recordActions([DetachAction::make()]);
    }

    private function resource(): Resource
    {
        $owner = $this->getOwnerRecord();

        assert($owner instanceof Resource);

        return $owner;
    }
}
