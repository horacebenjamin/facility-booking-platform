<?php

namespace App\Filament\Resources\Facilities\RelationManagers;

use App\Filament\Resources\Concerns\CanManageVenueConfiguration;
use App\Models\Facility;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

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
            CreateAction::make()->schema($this->allocationUnitForm()),
        ])->recordActions([
            EditAction::make()->schema($this->allocationUnitForm()),
            DeleteAction::make(),
        ]);
    }

    /** @return array<Component> */
    private function allocationUnitForm(): array
    {
        return [
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('code')->required()->maxLength(255)->unique(
                ignoreRecord: true,
                modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('facility_id', $this->facility()->id),
            ),
            Toggle::make('is_active')->label('Active')->default(true),
        ];
    }

    private function facility(): Facility
    {
        $owner = $this->getOwnerRecord();

        assert($owner instanceof Facility);

        return $owner;
    }
}
