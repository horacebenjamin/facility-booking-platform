<?php

namespace App\Actions;

use App\Enums\BookingPriceLineType;
use App\Enums\BookingStatus;
use App\Enums\FinancialStatus;
use App\Exceptions\BookingSubmissionUnavailable;
use App\Models\AllocationOccupancy;
use App\Models\Booking;
use App\Models\BookingEquipment;
use App\Models\BookingPriceLine;
use App\Models\BookingPriceSnapshot;
use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use App\Models\Resource;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\EquipmentRequirement;
use App\Services\OperationalOccupancyCalculator;
use App\Services\PriceQuote;
use App\Services\PricingRequest;
use App\Services\PricingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateBookingRequest
{
    public function __construct(
        private AvailabilityService $availabilityService,
        private OperationalOccupancyCalculator $operationalOccupancyCalculator,
        private PricingService $pricingService,
    ) {}

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipmentSelections
     */
    public function handle(
        User $customer,
        int $resourceId,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        array $equipmentSelections = [],
    ): Booking {
        return DB::transaction(function () use ($customer, $resourceId, $startsAt, $endsAt, $equipmentSelections): Booking {
            $resource = Resource::query()->lockForUpdate()->findOrFail($resourceId);
            $allocationUnits = $resource->allocationUnits()
                ->orderBy('allocation_units.id')
                ->lockForUpdate()
                ->get();

            if ($allocationUnits->isEmpty()) {
                throw new BookingSubmissionUnavailable;
            }

            $equipmentById = $this->lockedEquipment($equipmentSelections);

            $resource->load('facility.centre');
            $requirements = $this->equipmentRequirements($equipmentSelections, $equipmentById);
            $evaluatedAt = CarbonImmutable::now(config('app.timezone'));
            $availability = $this->availabilityService->check(
                $resource,
                $startsAt,
                $endsAt,
                $requirements,
                $evaluatedAt,
            );

            if (! $availability->isAvailable()) {
                throw new BookingSubmissionUnavailable;
            }

            $pricing = $this->pricingService->quote(new PricingRequest(
                $resource,
                $startsAt,
                $endsAt,
                $requirements,
            ));

            if (! $pricing->isSuccessful()) {
                throw new BookingSubmissionUnavailable;
            }

            $operationalPeriod = $this->operationalOccupancyCalculator->calculate($resource, $startsAt, $endsAt);

            if ($operationalPeriod === null) {
                throw new BookingSubmissionUnavailable;
            }

            $expiresAt = $evaluatedAt->addHours((int) config('booking.provisional_hold_hours'));
            $booking = Booking::query()->create([
                'reference' => $this->reference(),
                'customer_id' => $customer->id,
                'centre_id' => $resource->facility->centre_id,
                'facility_id' => $resource->facility_id,
                'resource_id' => $resource->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => BookingStatus::Requested,
                'financial_status' => FinancialStatus::NotDue,
            ]);

            $bookingEquipment = $this->createBookingEquipment($booking, $equipmentSelections, $equipmentById);
            $this->createSnapshot($booking, $pricing->quote, $bookingEquipment);

            $occupancy = AllocationOccupancy::query()->create([
                'booking_id' => $booking->id,
                'starts_at' => $operationalPeriod->startsAt,
                'ends_at' => $operationalPeriod->endsAt,
                'expires_at' => $expiresAt,
            ]);
            $occupancy->allocationUnits()->attach($allocationUnits->modelKeys());

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

            activity('booking')
                ->performedOn($booking)
                ->causedBy($customer)
                ->event('booking.requested')
                ->log('Booking requested');

            return $booking;
        });
    }

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipmentSelections
     * @return Collection<int, Equipment>
     */
    private function lockedEquipment(array $equipmentSelections): Collection
    {
        $equipmentIds = collect($equipmentSelections)->pluck('equipment_id')->sort()->values();

        if ($equipmentIds->isEmpty()) {
            return new Collection;
        }

        $equipment = Equipment::query()
            ->whereKey($equipmentIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

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
    private function createSnapshot(Booking $booking, ?PriceQuote $quote, array $bookingEquipment): void
    {
        if ($quote === null) {
            throw new BookingSubmissionUnavailable;
        }

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
