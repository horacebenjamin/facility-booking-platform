<?php

namespace App\Filament\Resources\Facilities\RelationManagers;

use App\Enums\DayOfWeek;
use App\Filament\Resources\Concerns\CanManageVenueConfiguration;
use App\Models\Facility;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class BookableHoursRelationManager extends RelationManager
{
    use CanManageVenueConfiguration;

    protected static string $relationship = 'bookableHours';

    protected static ?string $title = 'Customer-bookable hours';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('day_of_week')->formatStateUsing(fn (DayOfWeek $state): string => $state->name),
            TextColumn::make('opens_at')->time('H:i'),
            TextColumn::make('closes_at')->time('H:i'),
        ])->defaultSort('day_of_week')->headerActions([
            CreateAction::make()->schema($this->hoursForm()),
        ])->recordActions([
            EditAction::make()->schema($this->hoursForm()),
            DeleteAction::make(),
        ]);
    }

    /** @return array<Component> */
    private function hoursForm(): array
    {
        return [
            Select::make('day_of_week')->options($this->dayOptions())->required()->unique(
                ignoreRecord: true,
                modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('facility_id', $this->facility()->id),
            ),
            TimePicker::make('opens_at')->seconds(false)->required(),
            TimePicker::make('closes_at')->seconds(false)->required()->after('opens_at'),
        ];
    }

    /** @return array<int, string> */
    private function dayOptions(): array
    {
        return collect(DayOfWeek::cases())->mapWithKeys(fn (DayOfWeek $day): array => [$day->value => $day->name])->all();
    }

    private function facility(): Facility
    {
        $owner = $this->getOwnerRecord();

        assert($owner instanceof Facility);

        return $owner;
    }
}
