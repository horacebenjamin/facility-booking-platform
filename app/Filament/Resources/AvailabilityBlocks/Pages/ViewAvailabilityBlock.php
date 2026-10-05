<?php

namespace App\Filament\Resources\AvailabilityBlocks\Pages;

use App\Actions\EndAvailabilityBlock;
use App\Filament\Resources\AvailabilityBlocks\AvailabilityBlockResource;
use App\Models\AvailabilityBlock;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

class ViewAvailabilityBlock extends ViewRecord
{
    protected static string $resource = AvailabilityBlockResource::class;

    #[On('closure-impact-resolved')]
    public function refreshClosureSummary(): void
    {
        $this->record = $this->block();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('endClosure')->label('End closure')->requiresConfirmation()
                ->modalHeading('End closure')
                ->modalDescription('End this closure now. Otherwise valid availability becomes bookable again. Other closures still apply, and affected booking reviews remain unchanged.')
                ->visible(fn (): bool => $this->block()->ended_at === null && $this->block()->ends_at->isFuture() && auth()->user()?->can('end', $this->block()) === true)
                ->action(function (): mixed {
                    $actor = auth()->user();
                    abort_unless($actor instanceof User, 403);
                    try {
                        app(EndAvailabilityBlock::class)->handle($actor, $this->block());
                    } catch (ValidationException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();

                        return null;
                    }
                    Notification::make()->title('Closure ended. Affected booking reviews remain available.')->success()->send();

                    return $this->redirect(AvailabilityBlockResource::getUrl('view', ['record' => $this->getRecord()]));
                }),
        ];
    }

    private function block(): AvailabilityBlock
    {
        /** @var AvailabilityBlock $block */
        $block = AvailabilityBlockResource::getEloquentQuery()->findOrFail($this->getRecord()->getKey());

        return $block;
    }
}
