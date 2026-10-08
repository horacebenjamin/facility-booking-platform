<?php

namespace App\Filament\Pages;

use App\Actions\CreateAssistedCustomer;
use App\Actions\CreateManualBooking;
use App\Enums\AvailabilityReason;
use App\Enums\OrganisationRole;
use App\Exceptions\BookingSubmissionUnavailable;
use App\Models\Booking;
use App\Models\Centre;
use App\Models\Equipment;
use App\Models\Organisation;
use App\Models\Resource;
use App\Models\User;
use App\Services\BookingRequestEngine;
use App\Services\OrganisationBookingContext;
use App\Services\PricingContext;
use App\Services\PricingOverride;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateAssistedBooking extends Page
{
    protected static ?string $slug = 'assisted-booking';

    protected static ?string $title = 'Create assisted booking';

    protected static ?string $navigationLabel = 'Assisted booking';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Bookings';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.create-assisted-booking';

    protected ?string $subheading = 'Create a booking on behalf of a customer, including phone and walk-in bookings.';

    /** Changes to these properties invalidate a previously checked price. */
    private const array QUOTE_INPUT_PROPERTIES = ['startsAt', 'endsAt', 'selectedEquipmentIds', 'equipmentQuantities', 'overrideAmountMinor', 'overrideReason'];

    public ?int $customerId = null;

    public ?int $centreId = null;

    public ?int $resourceId = null;

    public ?int $organisationId = null;

    public string $customerSearch = '';

    public string $startsAt = '';

    public string $endsAt = '';

    /** @var list<int|string> */
    public array $selectedEquipmentIds = [];

    /** @var array<int|string, int|string> */
    public array $equipmentQuantities = [];

    public ?int $overrideAmountMinor = null;

    public string $overrideReason = '';

    public string $staffNote = '';

    /** @var array<string, int|string>|null */
    public ?array $quote = null;

    public ?string $successReference = null;

    public ?int $successBookingId = null;

    public ?string $errorMessage = null;

    public static function canAccess(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->can('createManual', Booking::class);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $startsAt = CarbonImmutable::now(self::localTimezone())->addDay()->startOfHour();
        $this->startsAt = $startsAt->format('Y-m-d\\TH:i');
        $this->endsAt = $startsAt->addHour()->format('Y-m-d\\TH:i');
        $this->centreId = $this->assignedCentres()->first()?->id;
    }

    public function updatedCentreId(): void
    {
        $this->resourceId = null;
        $this->selectedEquipmentIds = [];
        $this->equipmentQuantities = [];
        $this->quote = null;
    }

    public function updatedResourceId(): void
    {
        $this->quote = null;
    }

    public function updatedCustomerId(): void
    {
        $this->organisationId = null;
    }

    public function updated(string $property): void
    {
        if (in_array(Str::before($property, '.'), self::QUOTE_INPUT_PROPERTIES, true)) {
            $this->quote = null;
        }
    }

    public function updatedSelectedEquipmentIds(): void
    {
        foreach ($this->selectedEquipmentIds as $equipmentId) {
            if ((int) ($this->equipmentQuantities[$equipmentId] ?? 0) < 1) {
                $this->equipmentQuantities[$equipmentId] = 1;
            }
        }
    }

    /**
     * Clears the booking-specific selections so staff can take the next booking.
     */
    public function startNewBooking(): void
    {
        $this->reset([
            'customerId', 'customerSearch', 'organisationId', 'resourceId', 'selectedEquipmentIds', 'equipmentQuantities',
            'overrideAmountMinor', 'overrideReason', 'staffNote', 'quote', 'successReference', 'successBookingId', 'errorMessage',
        ]);
        $this->resetValidation();
    }

    public function createCustomerAction(): Action
    {
        return Action::make('createCustomer')
            ->label('Create new customer')
            ->icon('heroicon-o-user-plus')
            ->color('gray')
            ->outlined()
            ->authorize('createCustomer', User::class)
            ->modalIcon('heroicon-o-user-plus')
            ->modalHeading('Create new customer')
            ->modalDescription('Create an account for a caller or walk-in. They will be selected for this booking straight away.')
            ->modalSubmitActionLabel('Create and select customer')
            ->modalWidth(Width::Large)
            ->fillForm(fn (): array => [
                'organisation_mode' => 'personal',
                'organisation_role' => OrganisationRole::Member->value,
                ...$this->newCustomerDefaults(),
            ])
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('first_name')
                        ->label('First name')
                        ->required()
                        ->maxLength(120),
                    TextInput::make('last_name')
                        ->label('Last name')
                        ->required()
                        ->maxLength(120),
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(User::class, 'email')
                        ->validationMessages(['unique' => CreateAssistedCustomer::DUPLICATE_EMAIL_MESSAGE])
                        ->helperText('We’ll email the customer a secure link to set their password once the account is created.')
                        ->columnSpanFull(),
                    TextInput::make('phone')
                        ->label('Phone')
                        ->tel()
                        ->telRegex(CreateAssistedCustomer::PHONE_PATTERN)
                        ->validationMessages(['regex' => CreateAssistedCustomer::PHONE_FORMAT_MESSAGE])
                        ->maxLength(32)
                        ->helperText('Optional, but recommended for phone bookings.')
                        ->columnSpanFull(),
                    Radio::make('organisation_mode')
                        ->label('Organisation')
                        ->options([
                            'personal' => 'Personal customer only',
                            'existing' => 'Add to an existing organisation',
                            'new' => 'Create a new organisation',
                        ])
                        ->descriptions([
                            'personal' => 'No organisation membership.',
                            'existing' => 'For example, a new member of a club already on the system.',
                            'new' => 'The customer becomes the organisation’s owner.',
                        ])
                        ->default('personal')
                        ->required()
                        ->live()
                        ->visible(fn (): bool => $this->actor()->can('createForAssistedCustomer', Organisation::class))
                        ->columnSpanFull(),
                    Select::make('organisation_id')
                        ->label('Existing organisation')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => Organisation::query()
                            ->where('name', 'like', '%'.trim($search).'%')
                            ->orderBy('name')
                            ->limit(25)
                            ->pluck('name', 'id')
                            ->all())
                        ->getOptionLabelUsing(fn (int|string $value): ?string => Organisation::query()->find($value)?->name)
                        ->required()
                        ->visible(fn (Get $get): bool => $get('organisation_mode') === 'existing')
                        ->columnSpanFull(),
                    Select::make('organisation_role')
                        ->label('Membership role')
                        ->options(collect(OrganisationRole::staffAssignable())
                            ->mapWithKeys(fn (OrganisationRole $role): array => [$role->value => $role->label()])
                            ->all())
                        ->default(OrganisationRole::Member->value)
                        ->required()
                        ->helperText('Booking Manager and Admin can book for the organisation.')
                        ->visible(fn (Get $get): bool => $get('organisation_mode') === 'existing')
                        ->columnSpanFull(),
                    TextInput::make('organisation_name')
                        ->label('Organisation name')
                        ->required()
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('organisation_mode') === 'new')
                        ->columnSpanFull(),
                ]),
            ])
            ->action(function (array $data, Action $action): void {
                $organisationMode = $data['organisation_mode'] ?? 'personal';

                try {
                    $customer = app(CreateAssistedCustomer::class)->handle(
                        actor: $this->actor(),
                        firstName: (string) $data['first_name'],
                        lastName: (string) $data['last_name'],
                        email: (string) $data['email'],
                        phone: isset($data['phone']) ? (string) $data['phone'] : null,
                        existingOrganisationId: $organisationMode === 'existing' ? (int) $data['organisation_id'] : null,
                        existingOrganisationRole: $organisationMode === 'existing' ? OrganisationRole::tryFrom((string) $data['organisation_role']) : null,
                        newOrganisationName: $organisationMode === 'new' ? (string) $data['organisation_name'] : null,
                    );
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title('Customer could not be created')
                        ->body((string) Arr::first(Arr::flatten($exception->errors())))
                        ->danger()
                        ->send();
                    $action->halt();

                    return;
                }

                $membership = $customer->organisationMemberships()->with('organisation')->first();
                $canBookForOrganisation = $membership?->role->canCreateBookings() === true;

                $this->customerId = $customer->id;
                $this->organisationId = $canBookForOrganisation ? $membership->organisation_id : null;
                $this->customerSearch = $customer->email;
                $this->successReference = null;
                $this->successBookingId = null;
                $this->resetValidation('customerId');

                Notification::make()
                    ->title('Customer created and selected')
                    ->body(match (true) {
                        $membership === null => "{$customer->name} is now selected for this booking.",
                        $canBookForOrganisation => "{$customer->name} is now selected, booking for {$membership->organisation->name}.",
                        default => "{$customer->name} joined {$membership->organisation->name} as {$membership->role->label()}, so this is a personal booking.",
                    })
                    ->success()
                    ->send();
            });
    }

    public function preview(): void
    {
        $this->quote = null;
        $this->errorMessage = null;
        $this->successReference = null;
        $this->successBookingId = null;

        try {
            [$data, $startsAt, $endsAt, $equipmentSelections] = $this->validatedBookingInput();
            $actor = $this->actor();
            $this->authorizeSelectionScope($actor, $data['centreId'], $data['resourceId'], $equipmentSelections);
            $this->bookableOrganisation($this->selectedCustomerRecord($data['customerId']), $data['organisationId'] ?? null);
            $context = app(BookingRequestEngine::class)->context($data['resourceId'], $equipmentSelections);
            $validation = app(BookingRequestEngine::class)->validate(
                $context,
                $startsAt,
                $endsAt,
                CarbonImmutable::now(config('app.timezone')),
                pricingContext: $this->pricingContext($actor),
            );

            if (! $validation->availability->isAvailable()) {
                $this->errorMessage = 'This selection is unavailable: '.implode(', ', array_map(
                    fn (AvailabilityReason $reason): string => str_replace('_', ' ', $reason->value),
                    $validation->availability->reasons(),
                )).'.';

                return;
            }

            $quote = $validation->pricing?->quote;

            if ($quote === null) {
                $this->errorMessage = 'Pricing is not available for this selection.';

                return;
            }

            $this->quote = [
                'currency' => $quote->currency,
                'calculated_total_minor' => $quote->calculatedTotalMinor,
                'final_total_minor' => $quote->finalTotalMinor,
            ];
        } catch (BookingSubmissionUnavailable $exception) {
            $this->errorMessage = $exception->getMessage();
        }
    }

    public function submit(): void
    {
        $this->quote = null;
        $this->errorMessage = null;
        $this->successReference = null;
        $this->successBookingId = null;

        [$data, $startsAt, $endsAt, $equipmentSelections] = $this->validatedBookingInput();

        /** @var User $customer */
        $customer = User::query()->findOrFail($data['customerId']);
        $organisation = $this->bookableOrganisation($customer, $data['organisationId'] ?? null);

        try {
            $booking = app(CreateManualBooking::class)->handle(
                actor: $this->actor(),
                customer: $customer,
                centreId: $data['centreId'],
                resourceId: $data['resourceId'],
                startsAt: $startsAt,
                endsAt: $endsAt,
                equipmentSelections: $equipmentSelections,
                overrideAmountMinor: $data['overrideAmountMinor'],
                overrideReason: $data['overrideReason'],
                organisation: $organisation,
                staffNote: $data['staffNote'] ?? null,
            );
        } catch (BookingSubmissionUnavailable $exception) {
            $this->errorMessage = $exception->getMessage();
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        $this->successReference = $booking->reference;
        $this->successBookingId = $booking->id;
        Notification::make()->title('Assisted booking request created.')->success()->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);

        $centres = $this->assignedCentres();
        $customers = $this->customers();
        $resources = $this->resources();
        $equipmentOptions = $this->equipmentOptions();
        $selectedEquipmentIds = array_map('intval', $this->selectedEquipmentIds);
        $selectedCustomer = $customers->firstWhere('id', $this->customerId);
        $organisationOptions = $selectedCustomer instanceof User ? $this->organisationOptions($selectedCustomer) : collect();

        return [
            'centres' => $centres,
            'customers' => $customers,
            'resources' => $resources,
            'equipmentOptions' => $equipmentOptions,
            'selectedEquipmentIdList' => $selectedEquipmentIds,
            'selectedCustomer' => $selectedCustomer,
            'organisationOptions' => $organisationOptions,
            'selectedOrganisation' => $organisationOptions->firstWhere('id', $this->organisationId),
            'successBooking' => $this->successBookingId === null ? null : Booking::query()
                ->with('organisation')
                ->whereKey($this->successBookingId)
                ->whereIn('centre_id', $centres->modelKeys())
                ->first(),
            'localTimezone' => self::localTimezone(),
            'selectedCentre' => $centres->firstWhere('id', $this->centreId),
            'selectedResource' => $resources->firstWhere('id', $this->resourceId),
            'selectedEquipment' => $equipmentOptions->whereIn('id', $selectedEquipmentIds)->values(),
            'timeRange' => $this->timeRange(),
        ];
    }

    /**
     * Pre-fills the new customer form from whatever staff already typed into the search.
     *
     * @return array{first_name?: string, last_name?: string, email?: string, phone?: string}
     */
    private function newCustomerDefaults(): array
    {
        $search = trim($this->customerSearch);

        return match (true) {
            $search === '' => [],
            filter_var($search, FILTER_VALIDATE_EMAIL) !== false => ['email' => $search],
            preg_match(CreateAssistedCustomer::PHONE_PATTERN, $search) === 1 => ['phone' => $search],
            default => [
                'first_name' => Str::before($search, ' '),
                'last_name' => str_contains($search, ' ') ? trim(Str::after($search, ' ')) : '',
            ],
        };
    }

    /**
     * Organisations the customer may book for, using the same membership rules as self-service booking.
     *
     * @return SupportCollection<int, Organisation>
     */
    private function organisationOptions(User $customer): SupportCollection
    {
        return app(OrganisationBookingContext::class)
            ->bookableMemberships($customer)
            ->toBase()
            ->map(fn ($membership): Organisation => $membership->organisation)
            ->sortBy('name')
            ->values();
    }

    /**
     * Resolves the selected organisation only from the customer's bookable memberships,
     * so a tampered or stale organisation ID can never be attached to the booking.
     */
    private function bookableOrganisation(?User $customer, ?int $organisationId): ?Organisation
    {
        if ($organisationId === null) {
            return null;
        }

        $organisation = $customer === null ? null : $this->organisationOptions($customer)->firstWhere('id', $organisationId);

        if (! $organisation instanceof Organisation) {
            throw ValidationException::withMessages([
                'organisationId' => 'This customer cannot make bookings for the selected organisation.',
            ]);
        }

        return $organisation;
    }

    private function selectedCustomerRecord(int $customerId): ?User
    {
        return User::query()->role('customer')->find($customerId);
    }

    /** Staff enter and read times as local wall-clock time; the engine receives UTC instants. */
    private static function localTimezone(): string
    {
        return (string) config('booking.local_timezone');
    }

    /** @return array{starts: CarbonImmutable, ends: CarbonImmutable, minutes: int}|null */
    private function timeRange(): ?array
    {
        if ($this->startsAt === '' || $this->endsAt === '') {
            return null;
        }

        try {
            $startsAt = CarbonImmutable::parse($this->startsAt, self::localTimezone());
            $endsAt = CarbonImmutable::parse($this->endsAt, self::localTimezone());
        } catch (InvalidFormatException) {
            return null;
        }

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            return null;
        }

        return ['starts' => $startsAt, 'ends' => $endsAt, 'minutes' => (int) $startsAt->diffInMinutes($endsAt)];
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'customerId' => ['required', 'integer', 'exists:users,id'],
            'organisationId' => ['nullable', 'integer'],
            'centreId' => ['required', 'integer', 'exists:centres,id'],
            'resourceId' => ['required', 'integer', 'exists:resources,id'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
            'selectedEquipmentIds' => ['array'],
            'selectedEquipmentIds.*' => ['integer', 'distinct:strict', 'exists:equipment,id'],
            'equipmentQuantities' => ['array'],
            'equipmentQuantities.*' => ['required', 'integer', 'min:1'],
            'overrideAmountMinor' => ['nullable', 'integer', 'min:0'],
            'overrideReason' => [
                Rule::when(
                    $this->overrideAmountMinor !== null,
                    ['required'],
                    ['nullable'],
                ),
                'string',
                'max:2000',
            ],
            'staffNote' => ['nullable', 'string', 'max:'.CreateManualBooking::STAFF_NOTE_MAX_LENGTH],
        ];
    }

    /** @return array{0: array<string, mixed>, 1: CarbonImmutable, 2: CarbonImmutable, 3: list<array{equipment_id: int, quantity: int}>} */
    private function validatedBookingInput(): array
    {
        $data = $this->validate($this->rules());
        $startsAt = CarbonImmutable::parse($data['startsAt'], self::localTimezone())->setTimezone(config('app.timezone'));
        $endsAt = CarbonImmutable::parse($data['endsAt'], self::localTimezone())->setTimezone(config('app.timezone'));
        $equipmentSelections = [];

        foreach ($data['selectedEquipmentIds'] ?? [] as $equipmentId) {
            $equipmentSelections[] = [
                'equipment_id' => (int) $equipmentId,
                'quantity' => (int) ($data['equipmentQuantities'][$equipmentId] ?? $data['equipmentQuantities'][(string) $equipmentId] ?? 0),
            ];
        }

        return [$data, $startsAt, $endsAt, $equipmentSelections];
    }

    /** @return Collection<int, Centre> */
    private function assignedCentres(): Collection
    {
        return $this->actor()->assignedCentres()->orderBy('name')->get();
    }

    /** @return Collection<int, User> */
    private function customers(): Collection
    {
        $columns = ['id', 'name', 'email', 'phone'];
        $customers = User::query()
            ->role('customer')
            ->when(trim($this->customerSearch) !== '', fn ($query) => $query->where(function ($query): void {
                $search = trim($this->customerSearch);
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->limit(100)
            ->get($columns);

        if ($this->customerId !== null && ! $customers->contains('id', $this->customerId)) {
            $selectedCustomer = User::query()->role('customer')->find($this->customerId, $columns);

            if ($selectedCustomer instanceof User) {
                $customers->prepend($selectedCustomer);
            }
        }

        return $customers;
    }

    /** @return Collection<int, resource> */
    private function resources(): Collection
    {
        if ($this->centreId === null) {
            return new Collection;
        }

        return Resource::query()
            ->with('facility')
            ->whereHas('facility', fn ($query) => $query
                ->where('centre_id', $this->centreId)
                ->whereHas('centre.assignedUsers', fn ($query) => $query->whereKey($this->actor()->id)))
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Equipment> */
    private function equipmentOptions(): Collection
    {
        if ($this->centreId === null) {
            return new Collection;
        }

        return Equipment::query()
            ->where('centre_id', $this->centreId)
            ->whereHas('centre.assignedUsers', fn ($query) => $query->whereKey($this->actor()->id))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipmentSelections
     */
    private function authorizeSelectionScope(User $actor, int $centreId, int $resourceId, array $equipmentSelections): void
    {
        abort_unless($actor->assignedCentres()->whereKey($centreId)->exists(), 403);

        $resource = Resource::query()->with('facility')->findOrFail($resourceId);
        abort_unless($resource->facility->centre_id === $centreId, 403);

        $equipmentIds = collect($equipmentSelections)->pluck('equipment_id')->unique()->values();
        $equipment = Equipment::query()->whereKey($equipmentIds)->get();
        abort_unless($equipment->count() === $equipmentIds->count(), 403);
        abort_unless($equipment->every(fn (Equipment $item): bool => $item->centre_id === $centreId
            && ($item->facility_id === null || $item->facility_id === $resource->facility_id)), 403);
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        Gate::forUser($actor)->authorize('createManual', Booking::class);

        return $actor;
    }

    private function pricingContext(User $actor): ?PricingContext
    {
        if ($this->overrideAmountMinor === null) {
            return null;
        }

        return new PricingContext(override: new PricingOverride(
            actor: $actor,
            adjustedTotalMinor: (int) $this->overrideAmountMinor,
            reason: trim($this->overrideReason),
            adjustedAt: CarbonImmutable::now(config('app.timezone')),
        ));
    }
}
