<?php

namespace App\Actions;

use App\Enums\BillingMethod;
use App\Enums\BookingStatus;
use App\Enums\FinancialStatus;
use App\Events\LifecycleNotificationRequested;
use App\Exceptions\BookingLifecycleTransitionUnavailable;
use App\Exceptions\PaymentUnavailable;
use App\Models\AllocationOccupancy;
use App\Models\AllocationUnit;
use App\Models\Booking;
use App\Models\BookingEquipment;
use App\Models\CustomerInvoiceTerms;
use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use App\Models\Facility;
use App\Models\Organisation;
use App\Models\Resource;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\BookingPaymentEligibility;
use App\Services\CentreReservationLock;
use App\Services\EquipmentRequirement;
use App\Services\OperationalOccupancyCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

class ApproveBooking
{
    public function __construct(
        private AvailabilityService $availabilityService,
        private OperationalOccupancyCalculator $operationalOccupancyCalculator,
        private BookingPaymentEligibility $paymentEligibility,
        private CentreReservationLock $centreReservationLock,
    ) {}

    public function handle(User $actor, Booking $booking): Booking
    {
        $this->authorize($actor);
        Gate::forUser($actor)->authorize('approve', $booking);

        $centreId = $this->centreReservationLock->centreIdForBooking($booking->id);

        return DB::transaction(function () use ($actor, $booking, $centreId): Booking {
            $centre = $this->centreReservationLock->lock($centreId);
            $booking = Booking::query()
                ->lockForUpdate()
                ->findOrFail($booking->id);
            $booking->setRelation('centre', $centre);

            if ($booking->centre_id !== $centreId) {
                throw new BookingLifecycleTransitionUnavailable('The booking centre has changed. Refresh before approving.');
            }

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
                ->lockForUpdate()
                ->findOrFail($booking->resource_id);
            $facility = Facility::query()->where('centre_id', $centreId)->lockForUpdate()->find($resource->facility_id);
            if ($facility === null) {
                throw new BookingLifecycleTransitionUnavailable('The resource centre has changed. Refresh before approving.');
            }
            $facility->setRelation('centre', $centre);
            $resource->setRelation('facility', $facility);

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
                lockReservations: true,
            );

            if (! $availability->isAvailable()) {
                throw new BookingLifecycleTransitionUnavailable('This booking is no longer available for approval.');
            }

            if ($booking->organisation_id !== null) {
                Organisation::query()->lockForUpdate()->findOrFail($booking->organisation_id);
            }
            User::query()->lockForUpdate()->findOrFail($booking->customer_id);
            $terms = CustomerInvoiceTerms::query()->responsibleFor($booking)->where('enabled', true)->lockForUpdate()->first();
            if ($terms !== null) {
                try {
                    $this->paymentEligibility->snapshot($booking);
                } catch (PaymentUnavailable $exception) {
                    throw new BookingLifecycleTransitionUnavailable($exception->getMessage(), previous: $exception);
                }
            }

            $occupancy->update(['expires_at' => null]);
            EquipmentAllocation::query()
                ->where('booking_id', $booking->id)
                ->update(['expires_at' => null]);
            $booking->update([
                'status' => $terms === null ? BookingStatus::Approved : BookingStatus::Confirmed,
                'financial_status' => $terms === null ? FinancialStatus::AwaitingPayment : FinancialStatus::InvoiceOutstanding,
                'payment_due_at' => $terms === null ? $evaluatedAt->addHours(max(1, (int) config('booking.payment_deadline_hours'))) : null,
                'billing_method' => $terms === null ? BillingMethod::Card : BillingMethod::Invoice,
                'invoice_term_days' => $terms?->term_days,
            ]);

            $approvalActivity = activity('booking')
                ->performedOn($booking)
                ->causedBy($actor)
                ->event('booking.approved')
                ->withProperties([
                    'status' => $booking->status->value,
                    'financial_status' => $booking->financial_status->value,
                    'payment_due_at' => $booking->payment_due_at?->toIso8601String(),
                ])
                ->log('Booking approved');

            if ($terms === null && $approvalActivity instanceof Activity) {
                LifecycleNotificationRequested::dispatch($approvalActivity->id);
            }

            if ($terms !== null) {
                $confirmationActivity = activity('booking')->performedOn($booking)->causedBy($actor)->event('booking.confirmed_under_invoice_terms')
                    ->withProperties(['terms_id' => $terms->id, 'term_days' => $terms->term_days, 'before' => BookingStatus::Requested->value, 'after' => BookingStatus::Confirmed->value, 'financial_status' => $booking->financial_status->value])
                    ->log('Booking confirmed under authorised invoice terms');

                if ($confirmationActivity instanceof Activity) {
                    LifecycleNotificationRequested::dispatch($confirmationActivity->id);
                }
            }

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
