<?php

namespace App\Actions;

use App\Enums\BookingStatus;
use App\Enums\FinancialStatus;
use App\Exceptions\BookingLifecycleTransitionUnavailable;
use App\Models\AllocationOccupancy;
use App\Models\AllocationUnit;
use App\Models\Booking;
use App\Models\BookingEquipment;
use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use App\Models\Resource;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\EquipmentRequirement;
use App\Services\OperationalOccupancyCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ApproveBooking
{
    public function __construct(
        private AvailabilityService $availabilityService,
        private OperationalOccupancyCalculator $operationalOccupancyCalculator,
    ) {}

    public function handle(User $actor, Booking $booking): Booking
    {
        $this->authorize($actor);
        Gate::forUser($actor)->authorize('approve', $booking);

        return DB::transaction(function () use ($actor, $booking): Booking {
            $booking = Booking::query()
                ->with('centre')
                ->lockForUpdate()
                ->findOrFail($booking->id);

            $this->authorize($actor);
            Gate::forUser($actor)->authorize('approve', $booking);

            if ($booking->status !== BookingStatus::Requested) {
                throw new BookingLifecycleTransitionUnavailable('This booking is no longer awaiting management approval.');
            }

            $evaluatedAt = CarbonImmutable::now(config('app.timezone'));

            if ($booking->starts_at->lte($evaluatedAt)) {
                throw new BookingLifecycleTransitionUnavailable('This booking can no longer be approved because its start time has passed.');
            }

            $resource = Resource::query()
                ->with('facility.centre')
                ->lockForUpdate()
                ->findOrFail($booking->resource_id);
            $allocationUnits = $resource->allocationUnits()
                ->orderBy('allocation_units.id')
                ->lockForUpdate()
                ->get();

            $operationalPeriod = $this->operationalOccupancyCalculator->calculate(
                $resource,
                $booking->starts_at,
                $booking->ends_at,
            );

            $occupancy = AllocationOccupancy::query()
                ->where('booking_id', $booking->id)
                ->lockForUpdate()
                ->first();

            if ($operationalPeriod === null || ! $this->hasValidPhysicalProtection(
                $occupancy,
                $allocationUnits,
                $operationalPeriod->startsAt,
                $operationalPeriod->endsAt,
                $evaluatedAt,
            )) {
                throw new BookingLifecycleTransitionUnavailable('This booking no longer has valid provisional resource protection.');
            }

            $bookingEquipment = BookingEquipment::query()
                ->where('booking_id', $booking->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $equipment = $this->lockedEquipment($bookingEquipment);
            $equipmentAllocations = EquipmentAllocation::query()
                ->where('booking_id', $booking->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('booking_equipment_id');

            if (! $this->hasValidEquipmentProtection($booking, $bookingEquipment, $equipmentAllocations, $evaluatedAt)) {
                throw new BookingLifecycleTransitionUnavailable('This booking no longer has valid provisional equipment protection.');
            }

            $requirements = array_values($bookingEquipment
                ->map(fn (BookingEquipment $bookingEquipment): EquipmentRequirement => new EquipmentRequirement(
                    $equipment->sole('id', $bookingEquipment->equipment_id),
                    $bookingEquipment->requested_quantity,
                ))
                ->all());
            $availability = $this->availabilityService->check(
                $resource,
                $booking->starts_at,
                $booking->ends_at,
                $requirements,
                $evaluatedAt,
                $booking->id,
            );

            if (! $availability->isAvailable()) {
                throw new BookingLifecycleTransitionUnavailable('This booking is no longer available for approval.');
            }

            $occupancy->update(['expires_at' => null]);
            EquipmentAllocation::query()
                ->where('booking_id', $booking->id)
                ->update(['expires_at' => null]);
            $booking->update([
                'status' => BookingStatus::Approved,
                'financial_status' => FinancialStatus::AwaitingPayment,
            ]);

            activity('booking')
                ->performedOn($booking)
                ->causedBy($actor)
                ->event('booking.approved')
                ->withProperties([
                    'status' => BookingStatus::Approved->value,
                    'financial_status' => FinancialStatus::AwaitingPayment->value,
                ])
                ->log('Booking approved');

            return $booking->fresh() ?? $booking;
        });
    }

    /**
     * @param  Collection<int, AllocationUnit>  $allocationUnits
     */
    private function hasValidPhysicalProtection(
        ?AllocationOccupancy $occupancy,
        Collection $allocationUnits,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        CarbonImmutable $evaluatedAt,
    ): bool {
        if ($occupancy === null
            || $occupancy->expires_at === null
            || ! $occupancy->expires_at->gt($evaluatedAt)
            || ! $occupancy->starts_at->equalTo($startsAt)
            || ! $occupancy->ends_at->equalTo($endsAt)) {
            return false;
        }

        return $occupancy->allocationUnits()
            ->orderBy('allocation_units.id')
            ->pluck('allocation_units.id')
            ->all() === $allocationUnits->modelKeys();
    }

    /**
     * @param  Collection<int, BookingEquipment>  $bookingEquipment
     * @return Collection<int, Equipment>
     */
    private function lockedEquipment(Collection $bookingEquipment): Collection
    {
        if ($bookingEquipment->isEmpty()) {
            return new Collection;
        }

        $equipment = Equipment::query()
            ->whereKey($bookingEquipment->pluck('equipment_id')->sort()->values())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($equipment->count() !== $bookingEquipment->count()) {
            throw new BookingLifecycleTransitionUnavailable('This booking references unavailable equipment.');
        }

        return $equipment;
    }

    private function authorize(User $actor): void
    {
        if (! $actor->can('bookings.approve')) {
            throw new AuthorizationException;
        }
    }

    /**
     * @param  Collection<int, BookingEquipment>  $bookingEquipment
     * @param  Collection<int, EquipmentAllocation>  $equipmentAllocations
     */
    private function hasValidEquipmentProtection(
        Booking $booking,
        Collection $bookingEquipment,
        Collection $equipmentAllocations,
        CarbonImmutable $evaluatedAt,
    ): bool {
        if ($bookingEquipment->count() !== $equipmentAllocations->count()) {
            return false;
        }

        return $bookingEquipment->every(function (BookingEquipment $bookingEquipment) use ($booking, $equipmentAllocations, $evaluatedAt): bool {
            /** @var EquipmentAllocation|null $allocation */
            $allocation = $equipmentAllocations->get($bookingEquipment->id);

            return $allocation !== null
                && $allocation->equipment_id === $bookingEquipment->equipment_id
                && $allocation->quantity === $bookingEquipment->requested_quantity
                && $allocation->starts_at->equalTo($booking->starts_at)
                && $allocation->ends_at->equalTo($booking->ends_at)
                && $allocation->expires_at !== null
                && $allocation->expires_at->gt($evaluatedAt);
        });
    }
}
