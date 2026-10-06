<?php

namespace App\Services;

use App\Enums\BookingPriceLineType;
use App\Enums\BookingStatus;
use App\Enums\FinancialStatus;
use App\Events\LifecycleNotificationRequested;
use App\Exceptions\BookingSubmissionUnavailable;
use App\Models\AllocationOccupancy;
use App\Models\Booking;
use App\Models\BookingEquipment;
use App\Models\BookingPriceLine;
use App\Models\BookingPriceSnapshot;
use App\Models\BookingSeries;
use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class BookingRequestEngine
{
    public function __construct(
        private AvailabilityService $availabilityService,
        private OperationalOccupancyCalculator $operationalOccupancyCalculator,
        private PricingService $pricingService,
        private CentreReservationLock $centreReservationLock,
    ) {}

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipmentSelections
     */
    public function context(int $resourceId, array $equipmentSelections): BookingRequestContext
    {
        return $this->resolveContext($resourceId, $equipmentSelections, false);
    }

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipmentSelections
     */
    public function lockContext(int $resourceId, array $equipmentSelections, int $centreId): BookingRequestContext
    {
        return $this->resolveContext($resourceId, $equipmentSelections, true, $centreId);
    }

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipmentSelections
     */
    private function resolveContext(int $resourceId, array $equipmentSelections, bool $lock, ?int $centreId = null): BookingRequestContext
    {
        if ($lock) {
            $this->centreReservationLock->lock($centreId ?? throw new \LogicException('A centre is required for reservation locking.'));
        }

        $resourceQuery = Resource::query();

        if ($lock) {
            $resourceQuery->lockForUpdate();
        }

        $resource = $resourceQuery->findOrFail($resourceId);
        $resource->load('facility.centre');

        if ($lock && $resource->facility->centre_id !== $centreId) {
            throw new BookingSubmissionUnavailable;
        }

        $allocationUnitsQuery = $resource->allocationUnits()->orderBy('allocation_units.id');

        if ($lock) {
            $allocationUnitsQuery->lockForUpdate();
        }

        $allocationUnits = $allocationUnitsQuery->get();
        $equipmentById = $this->equipment($equipmentSelections, $lock);

        return new BookingRequestContext(
            resource: $resource,
            allocationUnits: $allocationUnits,
            equipmentById: $equipmentById,
            equipmentRequirements: $this->equipmentRequirements($equipmentSelections, $equipmentById),
        );
    }

    public function validate(
        BookingRequestContext $context,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        CarbonImmutable $evaluatedAt,
        ?int $excludedBookingId = null,
    ): BookingRequestValidation {
        $availability = $this->availabilityService->check(
            $context->resource,
            $startsAt,
            $endsAt,
            $context->equipmentRequirements,
            $evaluatedAt,
            $excludedBookingId,
        );
        $pricing = null;

        if (! $context->allocationUnits->isEmpty() && $availability->isAvailable()) {
            $pricing = $this->pricingService->quote(new PricingRequest(
                $context->resource,
                $startsAt,
                $endsAt,
                $context->equipmentRequirements,
            ));
        }

        return new BookingRequestValidation(
            availability: $availability,
            pricing: $pricing,
            operationalPeriod: $this->operationalOccupancyCalculator->calculate(
                $context->resource,
                $startsAt,
                $endsAt,
            ),
            hasAllocationUnits: ! $context->allocationUnits->isEmpty(),
        );
    }

    /**
     * Replace an existing request's protected period and authoritative price snapshot.
     * The caller must already hold the centre and booking locks.
     *
     * @param  list<array{equipment_id: int, quantity: int}>  $equipmentSelections
     */
    public function amend(
        Booking $booking,
        BookingRequestContext $context,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        array $equipmentSelections,
        BookingRequestValidation $validation,
        CarbonImmutable $evaluatedAt,
    ): Booking {
        $quote = $validation->pricing?->quote;
        $operationalPeriod = $validation->operationalPeriod;

        if (! $validation->canCreate() || $quote === null || $operationalPeriod === null) {
            throw new BookingSubmissionUnavailable('The amended booking details are no longer available.');
        }

        $booking->equipmentAllocations()->delete();

        $snapshot = $booking->priceSnapshot()->first();
        if ($snapshot !== null) {
            $snapshot->lines()->delete();
            $snapshot->delete();
        }

        $booking->equipmentRequests()->delete();

        $occupancy = $booking->allocationOccupancy()->first();
        if ($occupancy !== null) {
            $occupancy->allocationUnits()->detach();
            $occupancy->delete();
        }

        $booking->update([
            'centre_id' => $context->resource->facility->centre_id,
            'facility_id' => $context->resource->facility_id,
            'resource_id' => $context->resource->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $bookingEquipment = $this->createBookingEquipment(
            $booking,
            $equipmentSelections,
            $context->equipmentById,
        );
        $this->createSnapshot($booking, $quote, $bookingEquipment);

        $expiresAt = $evaluatedAt->addHours((int) config('booking.provisional_hold_hours'));
        $occupancy = AllocationOccupancy::query()->create([
            'booking_id' => $booking->id,
            'starts_at' => $operationalPeriod->startsAt,
            'ends_at' => $operationalPeriod->endsAt,
            'expires_at' => $expiresAt,
        ]);
        $occupancy->allocationUnits()->attach($context->allocationUnits->pluck('id')->all());

        foreach ($bookingEquipment as $equipmentRequest) {
            EquipmentAllocation::query()->create([
                'booking_id' => $booking->id,
                'booking_equipment_id' => $equipmentRequest->id,
                'equipment_id' => $equipmentRequest->equipment_id,
                'quantity' => $equipmentRequest->requested_quantity,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'expires_at' => $expiresAt,
            ]);
        }

        return $booking;
    }

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipmentSelections
     */
    public function persist(
        User $customer,
        BookingRequestContext $context,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        array $equipmentSelections,
        BookingRequestValidation $validation,
        CarbonImmutable $evaluatedAt,
        ?BookingSeries $series = null,
        ?int $occurrenceIndex = null,
    ): Booking {
        $quote = $validation->pricing?->quote;
        $operationalPeriod = $validation->operationalPeriod;

        if (! $validation->canCreate() || $quote === null || $operationalPeriod === null) {
            throw new BookingSubmissionUnavailable;
        }

        $expiresAt = $evaluatedAt->addHours((int) config('booking.provisional_hold_hours'));
        $booking = Booking::query()->create([
            'booking_series_id' => $series?->id,
            'occurrence_index' => $occurrenceIndex,
            'reference' => $this->reference(),
            'customer_id' => $customer->id,
            'centre_id' => $context->resource->facility->centre_id,
            'facility_id' => $context->resource->facility_id,
            'resource_id' => $context->resource->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => BookingStatus::Requested,
            'financial_status' => FinancialStatus::NotDue,
        ]);

        $bookingEquipment = $this->createBookingEquipment(
            $booking,
            $equipmentSelections,
            $context->equipmentById,
        );
        $this->createSnapshot($booking, $quote, $bookingEquipment);

        $occupancy = AllocationOccupancy::query()->create([
            'booking_id' => $booking->id,
            'starts_at' => $operationalPeriod->startsAt,
            'ends_at' => $operationalPeriod->endsAt,
            'expires_at' => $expiresAt,
        ]);
        $occupancy->allocationUnits()->attach($context->allocationUnits->pluck('id')->all());

        foreach ($bookingEquipment as $equipmentRequest) {
            EquipmentAllocation::query()->create([
                'booking_id' => $booking->id,
                'booking_equipment_id' => $equipmentRequest->id,
                'equipment_id' => $equipmentRequest->equipment_id,
                'quantity' => $equipmentRequest->requested_quantity,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'expires_at' => $expiresAt,
            ]);
        }

        $activity = activity('booking')
            ->performedOn($booking)
            ->causedBy($customer)
            ->event('booking.requested');

        if ($series !== null && $occurrenceIndex !== null) {
            $activity->withProperties([
                'booking_series_identifier' => $series->identifier,
                'occurrence_index' => $occurrenceIndex,
            ]);
        }

        $recordedActivity = $activity->log('Booking requested');

        if ($recordedActivity instanceof Activity) {
            LifecycleNotificationRequested::dispatch($recordedActivity->id);
        }

        return $booking;
    }

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipmentSelections
     * @return Collection<int, Equipment>
     */
    private function equipment(array $equipmentSelections, bool $lock): Collection
    {
        $equipmentIds = collect($equipmentSelections)->pluck('equipment_id')->sort()->values();

        if ($equipmentIds->isEmpty()) {
            return new Collection;
        }

        $equipmentQuery = Equipment::query()
            ->whereKey($equipmentIds)
            ->orderBy('id');

        if ($lock) {
            $equipmentQuery->lockForUpdate();
        }

        $equipment = $equipmentQuery->get()->keyBy('id');

        if ($equipment->count() !== $equipmentIds->count()) {
            throw new BookingSubmissionUnavailable;
        }

        return $equipment;
    }

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipmentSelections
     * @param  Collection<int, Equipment>  $equipmentById
     * @return list<EquipmentRequirement>
     */
    private function equipmentRequirements(array $equipmentSelections, Collection $equipmentById): array
    {
        return array_map(
            fn (array $selection): EquipmentRequirement => new EquipmentRequirement(
                $equipmentById->get($selection['equipment_id']),
                $selection['quantity'],
            ),
            $equipmentSelections,
        );
    }

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipmentSelections
     * @param  Collection<int, Equipment>  $equipmentById
     * @return list<BookingEquipment>
     */
    private function createBookingEquipment(Booking $booking, array $equipmentSelections, Collection $equipmentById): array
    {
        return array_map(
            fn (array $selection): BookingEquipment => BookingEquipment::query()->create([
                'booking_id' => $booking->id,
                'centre_id' => $booking->centre_id,
                'equipment_id' => $equipmentById->get($selection['equipment_id'])->id,
                'requested_quantity' => $selection['quantity'],
            ]),
            $equipmentSelections,
        );
    }

    /**
     * @param  list<BookingEquipment>  $bookingEquipment
     */
    private function createSnapshot(Booking $booking, PriceQuote $quote, array $bookingEquipment): void
    {
        $snapshot = BookingPriceSnapshot::query()->create([
            'booking_id' => $booking->id,
            'currency' => $quote->currency,
            'resource_amount_minor' => $quote->baseResourceAmountMinor,
            'equipment_amount_minor' => $quote->equipmentAmountMinor,
            'subtotal_minor' => $quote->subtotalMinor,
            'discount_requested_amount_minor' => $quote->discount?->requestedAmountMinor,
            'discount_amount_minor' => $quote->discount->amountMinor ?? 0,
            'discount_description' => $quote->discount?->description,
            'calculated_total_minor' => $quote->calculatedTotalMinor,
            'final_total_minor' => $quote->finalTotalMinor,
            'override_original_total_minor' => $quote->override?->originalTotalMinor,
            'override_adjusted_total_minor' => $quote->override?->adjustedTotalMinor,
            'override_reason' => $quote->override?->reason,
            'override_responsible_user_id' => $quote->override?->responsibleUserId,
            'override_responsible_user_name' => $quote->override?->responsibleUserName,
            'override_adjusted_at' => $quote->override?->adjustedAt,
        ]);

        BookingPriceLine::query()->create([
            'booking_price_snapshot_id' => $snapshot->id,
            'booking_id' => $booking->id,
            'line_type' => BookingPriceLineType::Resource,
            'description' => $quote->resourceLine->resourceName,
            'quantity' => 1,
            'charge_type' => null,
            'hourly_rate_minor' => $quote->resourceLine->hourlyRateMinor,
            'rate_unit' => $quote->resourceLine->rateUnit,
            'effective_from' => $quote->resourceLine->effectiveFrom,
            'effective_until' => $quote->resourceLine->effectiveUntil,
            'duration_seconds' => $quote->resourceLine->durationSeconds,
            'amount_minor' => $quote->resourceLine->amountMinor,
        ]);

        foreach ($quote->equipmentLines as $index => $line) {
            $equipmentRequest = $bookingEquipment[$index];

            BookingPriceLine::query()->create([
                'booking_price_snapshot_id' => $snapshot->id,
                'booking_id' => $booking->id,
                'booking_equipment_id' => $equipmentRequest->id,
                'line_type' => BookingPriceLineType::Equipment,
                'description' => $line->equipmentName,
                'quantity' => $line->quantity,
                'charge_type' => $line->chargeType,
                'hourly_rate_minor' => $line->hourlyRateMinor,
                'rate_unit' => $line->rateUnit,
                'effective_from' => $line->effectiveFrom,
                'effective_until' => $line->effectiveUntil,
                'duration_seconds' => $line->durationSeconds,
                'amount_minor' => $line->amountMinor,
            ]);
        }
    }

    private function reference(): string
    {
        return 'BKG-'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    }
}
