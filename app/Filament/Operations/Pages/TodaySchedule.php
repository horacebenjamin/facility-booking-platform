<?php

namespace App\Filament\Operations\Pages;

use App\Actions\CompleteBooking;
use App\Actions\CreateDamageReport;
use App\Actions\CreateIncident;
use App\Actions\RecordArrival;
use App\Actions\RecordNoShow;
use App\Exceptions\AttendanceTransitionUnavailable;
use App\Models\Booking;
use App\Models\Equipment;
use App\Models\User;
use App\Services\TodayScheduleService;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Gate;
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

    public function recordArrivalAction(): Action
    {
        return $this->attendanceAction('recordArrival', 'Booking arrived', RecordArrival::class,
            'Record that this booking has arrived. This does not change its financial obligations.');
    }

    public function recordNoShowAction(): Action
    {
        return $this->attendanceAction('recordNoShow', 'Mark as no-show', RecordNoShow::class,
            'Record that this booking did not attend. This cannot be undone here. Payment obligations remain unchanged.');
    }

    public function completeBookingAction(): Action
    {
        return $this->attendanceAction('completeBooking', 'Complete booking', CompleteBooking::class,
            'Record that this booking is operationally completed. This cannot be undone here. Payment obligations remain unchanged.');
    }

    public function reportIncidentAction(): Action
    {
        return Action::make('reportIncident')
            ->label('Report incident')
            ->modalHeading('Report operational incident')
            ->modalDescription(fn (array $arguments): string => $this->issueBooking($arguments)->reference.' · Incident remains internal and does not determine customer responsibility.')
            ->fillForm(fn (array $arguments): array => $this->issueContext($arguments, 'occurred_at'))
            ->schema([
                Hidden::make('centre_id'),
                Hidden::make('booking_id'),
                Hidden::make('resource_id'),
                Select::make('issue_type')->label('Issue type')->options([
                    'equipment_issue' => 'Equipment issue',
                    'facility_issue' => 'Facility issue',
                    'health_and_safety' => 'Health and safety',
                    'customer_conduct' => 'Customer conduct',
                    'other' => 'Other',
                ])->required(),
                TextInput::make('title')->required()->maxLength(255),
                DateTimePicker::make('occurred_at')->label('Occurred at')->native()->timezone(config('app.timezone'))->format('Y-m-d H:i:s')->required(),
                Textarea::make('description')->required()->maxLength(5000)->columnSpanFull(),
                Textarea::make('immediate_action')->label('Immediate action taken')->maxLength(5000)->columnSpanFull(),
            ])
            ->action(function (array $data, array $arguments): void {
                $staff = auth()->user();
                abort_unless($staff instanceof User, 403);
                $booking = $this->issueBooking($arguments);
                app(CreateIncident::class)->handle($staff, [...$data, 'centre_id' => $booking->centre_id, 'booking_id' => $booking->id, 'resource_id' => $booking->resource_id]);
                Notification::make()->title('Incident recorded.')->success()->send();
            });
    }

    public function reportDamageAction(): Action
    {
        return Action::make('reportDamage')
            ->label('Report damage')
            ->modalHeading('Report equipment or facility damage')
            ->modalDescription(fn (array $arguments): string => $this->issueBooking($arguments)->reference.' · Responsibility and any financial follow-up require management review.')
            ->fillForm(fn (array $arguments): array => $this->issueContext($arguments, 'observed_at'))
            ->schema([
                Hidden::make('centre_id'),
                Hidden::make('booking_id'),
                Hidden::make('resource_id'),
                Select::make('equipment_id')->label('Equipment')->options(fn (Get $get): array => Equipment::query()->where('centre_id', $get('centre_id'))->orderBy('name')->pluck('name', 'id')->all())->searchable()->preload(),
                DateTimePicker::make('observed_at')->label('Observed at')->native()->timezone(config('app.timezone'))->format('Y-m-d H:i:s')->required(),
                Textarea::make('description')->required()->maxLength(5000)->columnSpanFull(),
            ])
            ->action(function (array $data, array $arguments): void {
                $staff = auth()->user();
                abort_unless($staff instanceof User, 403);
                $booking = $this->issueBooking($arguments);
                app(CreateDamageReport::class)->handle($staff, [...$data, 'centre_id' => $booking->centre_id, 'booking_id' => $booking->id, 'resource_id' => $booking->resource_id]);
                Notification::make()->title('Damage report recorded.')->success()->send();
            });
    }

    /** @param class-string<RecordArrival|RecordNoShow|CompleteBooking> $handler */
    private function attendanceAction(string $name, string $label, string $handler, string $description): Action
    {
        return Action::make($name)
            ->label($label)
            ->requiresConfirmation()
            ->modalHeading($label)
            ->modalDescription(function (array $arguments) use ($description): string {
                $booking = $this->attendanceBooking($arguments);

                return $booking->reference.' · '.$booking->facility->name.' · '.$booking->resource->name.' · '.$booking->starts_at->format('d M H:i').'–'.$booking->ends_at->format('H:i').'. '.$description;
            })
            ->mountUsing(function (array $arguments): void {
                $this->attendanceBooking($arguments);
            })
            ->action(function (array $arguments) use ($handler, $label): void {
                $booking = $this->attendanceBooking($arguments);
                $staff = auth()->user();
                abort_unless($staff instanceof User, 403);

                try {
                    app($handler)->handle($staff, $booking);
                } catch (AttendanceTransitionUnavailable $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title($label.' recorded.')->success()->send();
            });
    }

    /** @param array<string, mixed> $arguments */
    private function attendanceBooking(array $arguments): Booking
    {
        abort_unless(static::canAccess(), 403);
        $staff = auth()->user();
        abort_unless($staff instanceof User, 403);

        $centres = app(TodayScheduleService::class)->authorizedCentres($staff);
        abort_unless($this->centreId !== null && $centres->contains('id', $this->centreId), 403);
        $bookingId = $arguments['booking'] ?? null;
        abort_unless(filter_var($bookingId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false, 403);
        $booking = Booking::query()->where('centre_id', $this->centreId)
            ->with(['facility:id,name', 'resource:id,name'])
            ->findOrFail((int) $bookingId);
        Gate::authorize('manageAttendance', $booking);

        return $booking;
    }

    /** @param array<string, mixed> $arguments */
    private function issueBooking(array $arguments): Booking
    {
        abort_unless(static::canAccess(), 403);
        $staff = auth()->user();
        abort_unless($staff instanceof User && $staff->can('incidents.manage'), 403);
        $centres = app(TodayScheduleService::class)->authorizedCentres($staff);
        abort_unless($this->centreId !== null && $centres->contains('id', $this->centreId), 403);
        $bookingId = $arguments['booking'] ?? null;
        abort_unless(filter_var($bookingId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false, 403);
        $booking = Booking::query()->where('centre_id', $this->centreId)
            ->with(['facility:id,name', 'resource:id,name'])
            ->findOrFail((int) $bookingId);
        Gate::authorize('view', $booking);

        return $booking;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function issueContext(array $arguments, string $timestampField): array
    {
        $booking = $this->issueBooking($arguments);

        return [
            'centre_id' => $booking->centre_id,
            'booking_id' => $booking->id,
            'resource_id' => $booking->resource_id,
            $timestampField => CarbonImmutable::now(config('app.timezone'))->format('Y-m-d H:i:s'),
        ];
    }

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
