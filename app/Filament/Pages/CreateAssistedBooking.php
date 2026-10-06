<?php

namespace App\Filament\Pages;

use App\Actions\CreateManualBooking;
use App\Enums\AvailabilityReason;
use App\Exceptions\BookingSubmissionUnavailable;
use App\Models\Booking;
use App\Models\Centre;
use App\Models\Equipment;
use App\Models\Resource;
use App\Models\User;
use App\Services\BookingRequestEngine;
use App\Services\PricingContext;
use App\Services\PricingOverride;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CreateAssistedBooking extends Page
{
    protected static ?string $slug = 'assisted-booking';

    protected static ?string $title = 'Create assisted booking';

    protected static ?string $navigationLabel = 'Assisted booking';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Bookings';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.create-assisted-booking';

    public ?int $customerId = null;

    public ?int $centreId = null;

    public ?int $resourceId = null;

    public string $customerSearch = '';

    public string $startsAt = '';

    public string $endsAt = '';

    /** @var list<int|string> */
    public array $selectedEquipmentIds = [];

    /** @var array<int|string, int|string> */
    public array $equipmentQuantities = [];

    public ?int $overrideAmountMinor = null;

    public string $overrideReason = '';

    /** @var array<string, int|string>|null */
    public ?array $quote = null;

    public ?string $successReference = null;

    public ?string $errorMessage = null;

    public static function canAccess(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->can('createManual', Booking::class);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $startsAt = CarbonImmutable::now(config('app.timezone'))->addDay()->startOfHour();
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

    public function preview(): void
    {
        $this->quote = null;
        $this->errorMessage = null;
        $this->successReference = null;

        try {
            [$data, $startsAt, $endsAt, $equipmentSelections] = $this->validatedBookingInput();
            $actor = $this->actor();
            $this->authorizeSelectionScope($actor, $data['centreId'], $data['resourceId'], $equipmentSelections);
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

        [$data, $startsAt, $endsAt, $equipmentSelections] = $this->validatedBookingInput();

        /** @var User $customer */
        $customer = User::query()->findOrFail($data['customerId']);

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
            );
        } catch (BookingSubmissionUnavailable $exception) {
            $this->errorMessage = $exception->getMessage();
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        $this->successReference = $booking->reference;
        Notification::make()->title('Assisted booking request created.')->success()->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);

        return [
            'centres' => $this->assignedCentres(),
            'customers' => $this->customers(),
            'resources' => $this->resources(),
            'equipmentOptions' => $this->equipmentOptions(),
        ];
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'customerId' => ['required', 'integer', 'exists:users,id'],
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
        ];
    }

    /** @return array{0: array<string, mixed>, 1: CarbonImmutable, 2: CarbonImmutable, 3: list<array{equipment_id: int, quantity: int}>} */
    private function validatedBookingInput(): array
    {
        $data = $this->validate($this->rules());
        $startsAt = CarbonImmutable::parse($data['startsAt'], config('app.timezone'))->setTimezone(config('app.timezone'));
        $endsAt = CarbonImmutable::parse($data['endsAt'], config('app.timezone'))->setTimezone(config('app.timezone'));
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
        return User::query()
            ->role('customer')
            ->when(trim($this->customerSearch) !== '', fn ($query) => $query->where(function ($query): void {
                $search = trim($this->customerSearch);
                $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'email']);
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
