<?php

namespace App\Filament\Resources\Resources\RelationManagers;

use App\Filament\Resources\Concerns\CanManageVenueConfiguration;
use App\Models\AllocationUnit;
use App\Models\Resource;
use App\Models\User;
use App\Services\ResourceAllocationConfiguration;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Hidden;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

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
                ])
                ->using(function (AttachAction $action, array $data): void {
                    $actor = auth()->user();
                    assert($actor instanceof User);
                    try {
                        app(ResourceAllocationConfiguration::class)->attach($actor, $this->resource(), (int) $data['recordId']);
                    } catch (ValidationException $exception) {
                        $this->rejectChange($action, $exception);
                    }
                }),
        ])->recordActions([
            DetachAction::make()->using(function (DetachAction $action, AllocationUnit $record): void {
                $actor = auth()->user();
                assert($actor instanceof User);
                try {
                    app(ResourceAllocationConfiguration::class)->detach($actor, $this->resource(), $record->id);
                } catch (ValidationException $exception) {
                    $this->rejectChange($action, $exception);
                }
            }),
        ]);
    }

    private function resource(): Resource
    {
        $owner = $this->getOwnerRecord();

        assert($owner instanceof Resource);

        return $owner;
    }

    private function rejectChange(Action $action, ValidationException $exception): void
    {
        Notification::make()->title('Allocation units could not be changed')
            ->body((string) Arr::first(Arr::flatten($exception->errors())))->danger()->send();
        $action->halt();
    }
}
