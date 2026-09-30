<?php

namespace App\Filament\Resources\Centres\RelationManagers;

use App\Filament\Resources\Concerns\CanManageVenueConfiguration;
use App\Models\Centre;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class FacilitiesRelationManager extends RelationManager
{
    use CanManageVenueConfiguration;

    protected static string $relationship = 'facilities';

    protected static ?string $title = 'Facilities';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('slug'),
            TextColumn::make('capacity')->numeric(),
            IconColumn::make('is_active')->label('Active')->boolean(),
        ])->headerActions([
            CreateAction::make()->schema($this->facilityForm()),
        ])->recordActions([
            EditAction::make()->schema($this->facilityForm()),
            DeleteAction::make(),
        ]);
    }

    /** @return array<Component> */
    private function facilityForm(): array
    {
        return [
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('slug')->required()->maxLength(255)->unique(
                ignoreRecord: true,
                modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('centre_id', $this->centre()->id),
            ),
            TextInput::make('capacity')->numeric()->minValue(1),
            Toggle::make('is_active')->label('Active')->default(true),
            Textarea::make('description')->columnSpanFull(),
        ];
    }

    private function centre(): Centre
    {
        $owner = $this->getOwnerRecord();

        assert($owner instanceof Centre);

        return $owner;
    }
}
