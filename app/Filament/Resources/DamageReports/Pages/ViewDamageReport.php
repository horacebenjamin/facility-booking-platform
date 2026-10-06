<?php

namespace App\Filament\Resources\DamageReports\Pages;

use App\Actions\ReviewDamageReport;
use App\Enums\DamageFinancialFollowUp;
use App\Enums\DamageResponsibility;
use App\Enums\OperationalIssueStatus;
use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Models\DamageReport;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewDamageReport extends ViewRecord
{
    protected static string $resource = DamageReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('review')->label('Record follow-up')
                ->visible(fn (): bool => $this->report()->status !== OperationalIssueStatus::Closed && auth()->user()?->can('review', $this->report()) === true)
                ->fillForm(fn (): array => ['status' => $this->report()->status->nextStates()[0]->value, 'responsibility' => $this->report()->responsibility->value, 'financial_follow_up' => $this->report()->financial_follow_up->value, 'follow_up_notes' => $this->report()->follow_up_notes])
                ->schema([
                    Select::make('status')->options(fn (): array => collect($this->report()->status->nextStates())->mapWithKeys(fn (OperationalIssueStatus $status): array => [$status->value => $status->label()])->all())->required(),
                    Select::make('responsibility')->options(DamageResponsibility::options())->required(),
                    Select::make('financial_follow_up')->label('Financial follow-up')->options(DamageFinancialFollowUp::options())->required(),
                    Textarea::make('follow_up_notes')->label('Follow-up notes')->required()->maxLength(5000),
                ])
                ->action(function (array $data): void {
                    $actor = auth()->user();
                    abort_unless($actor instanceof User, 403);
                    app(ReviewDamageReport::class)->handle($actor, $this->report(), $data);
                    Notification::make()->title('Damage report follow-up recorded.')->success()->send();
                }),
        ];
    }

    private function report(): DamageReport
    {
        /** @var DamageReport $report */
        $report = DamageReportResource::getEloquentQuery()->findOrFail($this->getRecord()->getKey());

        return $report;
    }
}
