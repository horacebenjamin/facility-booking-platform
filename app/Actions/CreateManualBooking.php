<?php

namespace App\Actions;

use App\Exceptions\BookingSubmissionUnavailable;
use App\Models\Booking;
use App\Models\Equipment;
use App\Models\Resource;
use App\Models\User;
use App\Services\PricingContext;
use App\Services\PricingOverride;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidationValidator;

class CreateManualBooking
{
    public function __construct(private CreateBookingRequest $createBookingRequest) {}

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipmentSelections
     */
    public function handle(
        User $actor,
        User $customer,
        int $centreId,
        int $resourceId,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        array $equipmentSelections = [],
        ?int $overrideAmountMinor = null,
        ?string $overrideReason = null,
    ): Booking {
        Gate::forUser($actor)->authorize('createManual', Booking::class);
        $this->validateInput($startsAt, $endsAt, $equipmentSelections, $overrideAmountMinor, $overrideReason);

        $resource = Resource::query()->with('facility.centre')->findOrFail($resourceId);

        if ($resource->facility->centre_id !== $centreId || ! $actor->isAssignedToCentre($resource->facility->centre)) {
            throw new AuthorizationException;
        }

        $customer = User::query()->findOrFail($customer->id);

        if (! $customer->hasRole('customer')) {
            throw new BookingSubmissionUnavailable('Select an existing customer account.');
        }

        $this->assertEquipmentRelationships($equipmentSelections, $centreId, $resource->facility_id);

        $pricingContext = $overrideAmountMinor === null
            ? null
            : new PricingContext(
                override: new PricingOverride(
                    actor: $actor,
                    adjustedTotalMinor: $overrideAmountMinor,
                    reason: trim((string) $overrideReason),
                    adjustedAt: CarbonImmutable::now(config('app.timezone')),
                ),
            );

        return $this->createBookingRequest->handle(
            customer: $customer,
            resourceId: $resourceId,
            startsAt: $startsAt,
            endsAt: $endsAt,
            equipmentSelections: $equipmentSelections,
            pricingContext: $pricingContext,
            actor: $actor,
        );
    }

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipmentSelections
     */
    private function validateInput(
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        array $equipmentSelections,
        ?int $overrideAmountMinor,
        ?string $overrideReason,
    ): void {
        Validator::make([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'equipment' => $equipmentSelections,
            'override_amount_minor' => $overrideAmountMinor,
            'override_reason' => $overrideReason,
        ], [
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'equipment' => ['array'],
            'equipment.*.equipment_id' => ['required', 'integer', 'min:1', 'distinct:strict'],
            'equipment.*.quantity' => ['required', 'integer', 'min:1'],
            'override_amount_minor' => ['nullable', 'integer', 'min:0'],
            'override_reason' => ['nullable', 'string', 'max:2000'],
        ])->after(function (ValidationValidator $validator) use ($overrideAmountMinor, $overrideReason): void {
            $hasReason = trim((string) $overrideReason) !== '';

            if (($overrideAmountMinor === null) === $hasReason) {
                $validator->errors()->add('override_reason', 'A reason is required for a price override.');
            }
        })->validate();
    }

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipmentSelections
     */
    private function assertEquipmentRelationships(array $equipmentSelections, int $centreId, int $facilityId): void
    {
        $equipmentIds = collect($equipmentSelections)->pluck('equipment_id')->unique()->values();

        if ($equipmentIds->isEmpty()) {
            return;
        }

        $equipment = Equipment::query()->whereKey($equipmentIds)->get();

        if ($equipment->count() !== $equipmentIds->count()
            || $equipment->contains(fn (Equipment $item): bool => $item->centre_id !== $centreId
                || ($item->facility_id !== null && $item->facility_id !== $facilityId))) {
            throw new BookingSubmissionUnavailable('Selected equipment is not available at the selected centre or facility.');
        }
    }
}
