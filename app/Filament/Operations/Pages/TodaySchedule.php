<?php

namespace App\Filament\Operations\Pages;

use App\Models\User;
use App\Services\TodayScheduleService;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Url;

class TodaySchedule extends Page
{
    protected static ?string $slug = 'today-schedule';

    protected static ?string $title = 'Today’s Schedule';

    protected static ?int $navigationSort = -10;

    protected string $view = 'filament.operations.pages.today-schedule';

    #[Url]
    public ?int $centreId = null;

    #[Url]
    public string $date = '';

    public static function canAccess(): bool
    {
        $staff = auth()->user();

        return $staff instanceof User
            && $staff->hasRole('leisure-assistant')
            && $staff->can('bookings.view');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        if ($this->date === '') {
            $this->date = CarbonImmutable::now(config('app.timezone'))->toDateString();
        }

        $staff = auth()->user();
        abort_unless($staff instanceof User, 403);

        if ($this->centreId === null) {
            $this->centreId = app(TodayScheduleService::class)->authorizedCentres($staff)->first()?->id;
        }
    }

    /** Trigger a fresh authoritative query through rendering. */
    public function refreshSchedule(): void {}

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $staff = auth()->user();
        abort_unless($staff instanceof User, 403);

        $service = app(TodayScheduleService::class);
        $centres = $service->authorizedCentres($staff);
        $centre = $this->centreId === null ? null : $centres->firstWhere('id', $this->centreId);

        if ($this->centreId !== null || $centres->isNotEmpty()) {
            abort_unless($centre !== null, 403);
        }

        $validator = Validator::make(['date' => $this->date], ['date' => ['required', 'date_format:Y-m-d']]);
        $this->resetValidation('date');

        foreach ($validator->errors()->get('date') as $message) {
            $this->addError('date', $message);
        }

        return [
            'centres' => $centres,
            'selectedCentre' => $centre,
            'schedule' => $centre === null || $validator->fails() ? null : $service->forDate($staff, $centre, $this->date),
            'timezone' => config('app.timezone'),
        ];
    }
}
