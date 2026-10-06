<?php

namespace App\Actions;

use App\Exceptions\BookingLifecycleTransitionUnavailable;
use App\Exceptions\BookingSubmissionUnavailable;
use App\Models\Booking;
use App\Models\BookingEquipment;
use App\Models\User;
use App\Services\BookingRequestEngine;
use App\Services\CentreReservationLock;
use App\Services\CustomerBookingManagementPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AmendBooking
{
    public function __construct(
        private BookingRequestEngine $bookingRequestEngine,
        private CentreReservationLock $centreReservationLock,
        private CustomerBookingManagementPolicy $managementPolicy,
    ) {}

    /**
     * @param  list<array{equipment_id: int, quantity: int}>|null  $equipmentSelections
     */
    public function handle(
        User $actor,
        Booking $booking,
        int $resourceId,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        string $scope,
        ?array $equipmentSelections = null,
    ): Booking {
        if ($scope !== 'occurrence') {
            throw new BookingLifecycleTransitionUnavailable('Choose exactly which recurring occurrence to amend.');
        }

        $this->authorize($actor, $booking);
        $centreId = $this->centreReservationLock->centreIdForBooking($booking->id);
        $resourceCentreId = $this->centreReservationLock->centreIdForResource($resourceId);

        if ($centreId !== $resourceCentreId) {
            throw new BookingLifecycleTransitionUnavailable('An amendment must remain within the original centre.');
        }

        return DB::transaction(function () use ($actor, $booking, $resourceId, $startsAt, $endsAt, $scope, $equipmentSelections, $centreId): Booking {
            $this->centreReservationLock->lock($centreId);
            $booking = Booking::query()->with('centre')->lockForUpdate()->findOrFail($booking->id);
            $this->authorize($actor, $booking);

            $evaluatedAt = CarbonImmutable::now(config('app.timezone'));
            $unavailableReason = $this->managementPolicy->amendmentUnavailableReason($booking, $evaluatedAt);

            if ($unavailableReason !== null) {
                throw new BookingLifecycleTransitionUnavailable($unavailableReason);
            }

            if ($booking->financial_status->value !== 'not_due') {
                throw new BookingLifecycleTransitionUnavailable('This booking has a financial obligation and cannot be amended online.');
            }

            $equipmentSelections ??= BookingEquipment::query()
                ->where('booking_id', $booking->id)
                ->orderBy('id')
                ->get(['equipment_id', 'requested_quantity'])
                ->map(fn (BookingEquipment $equipment): array => [
                    'equipment_id' => $equipment->equipment_id,
                    'quantity' => $equipment->requested_quantity,
                ])
                ->all();
            $equipmentSelections = array_values($equipmentSelections);

            $context = $this->bookingRequestEngine->lockContext($resourceId, $equipmentSelections, $centreId);
            $validation = $this->bookingRequestEngine->validate(
                $context,
                $startsAt,
                $endsAt,
                $evaluatedAt,
                $booking->id,
            );

            if (! $validation->canCreate()) {
                throw new BookingSubmissionUnavailable('The requested booking period is no longer available.');
            }

            $original = [
                'resource_id' => $booking->resource_id,
                'starts_at' => $booking->starts_at->toIso8601String(),
                'ends_at' => $booking->ends_at->toIso8601String(),
            ];

            $amended = $this->bookingRequestEngine->amend(
                booking: $booking,
                context: $context,
                startsAt: $startsAt,
                endsAt: $endsAt,
                equipmentSelections: $equipmentSelections,
                validation: $validation,
                evaluatedAt: $evaluatedAt,
            );

            activity('booking')
                ->performedOn($amended)
                ->causedBy($actor)
                ->event('booking.amended')
                ->withProperties([
                    'scope' => $scope,
                    'before' => $original,
                    'after' => [
                        'resource_id' => $amended->resource_id,
                        'starts_at' => $amended->starts_at->toIso8601String(),
                        'ends_at' => $amended->ends_at->toIso8601String(),
                    ],
                ])
                ->log('Booking amended');

            return $amended->fresh() ?? $amended;
        });
    }

    private function authorize(User $actor, Booking $booking): void
    {
        if (! $actor->can('bookings.amend')) {
            throw new AuthorizationException;
        }

        Gate::forUser($actor)->authorize('amend', $booking);
    }
}
