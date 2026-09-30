<?php

namespace App\Filament\Resources\Facilities\RelationManagers;

use App\Filament\Resources\Concerns\CanManageVenueConfiguration;
use App\Models\Facility;
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

class ResourcesRelationManager extends RelationManager
{
    use CanManageVenueConfiguration;

    protected static string $relationship = 'resources';

    protected static ?string $title = 'Resources';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('slug'),
            TextColumn::make('setup_minutes')->label('Setup')->suffix(' min'),
            TextColumn::make('cleanup_minutes')->label('Cleanup')->suffix(' min'),
            IconColumn::make('is_active')->label('Active')->boolean(),
        ])->headerActions([
            CreateAction::make()->schema($this->resourceForm()),
        ])->recordActions([
            EditAction::make()->schema($this->resourceForm()),
            DeleteAction::make(),
        ]);
    }

    /** @return array<Component> */
    private function resourceForm(): array
    {
        return [
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('slug')->required()->maxLength(255)->unique(
                ignoreRecord: true,
                modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('facility_id', $this->facility()->id),
            ),
            TextInput::make('capacity')->numeric()->minValue(1),
            TextInput::make('setup_minutes')->label('Setup minutes')->numeric()->integer()->minValue(0)->default(0)->required(),
            TextInput::make('cleanup_minutes')->label('Cleanup minutes')->numeric()->integer()->minValue(0)->default(0)->required(),
            Toggle::make('is_active')->label('Active')->default(true),
            Textarea::make('description')->columnSpanFull(),
        ];
    }

    private function facility(): Facility
    {
        $owner = $this->getOwnerRecord();

        assert($owner instanceof Facility);

        return $owner;
    }
}
