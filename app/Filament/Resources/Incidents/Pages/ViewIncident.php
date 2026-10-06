<?php

namespace App\Filament\Resources\Incidents\Pages;

use App\Actions\ReviewIncident;
use App\Enums\OperationalIssueStatus;
use App\Filament\Resources\Incidents\IncidentResource;
use App\Models\Incident;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewIncident extends ViewRecord
{
    protected static string $resource = IncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('review')->label('Record follow-up')
                ->visible(fn (): bool => $this->incident()->status !== OperationalIssueStatus::Closed && auth()->user()?->can('review', $this->incident()) === true)
                ->fillForm(fn (): array => ['status' => $this->incident()->status->nextStates()[0]->value, 'follow_up_notes' => $this->incident()->follow_up_notes])
                ->schema([
                    Select::make('status')->options(fn (): array => collect($this->incident()->status->nextStates())->mapWithKeys(fn (OperationalIssueStatus $status): array => [$status->value => $status->label()])->all())->required(),
                    Textarea::make('follow_up_notes')->label('Follow-up notes')->required()->maxLength(5000),
                ])
                ->action(function (array $data): void {
                    $actor = auth()->user();
                    abort_unless($actor instanceof User, 403);
                    app(ReviewIncident::class)->handle($actor, $this->incident(), $data);
                    $this->record = $this->incident();
                    $this->getSchema('infolist')?->record($this->record);
                    Notification::make()->title('Incident follow-up recorded.')->success()->send();
                }),
        ];
    }

    private function incident(): Incident
    {
        /** @var Incident $incident */
        $incident = IncidentResource::getEloquentQuery()->findOrFail($this->getRecord()->getKey());

        return $incident;
    }
}
