<?php

namespace App\Actions;

use App\Enums\BookingStatus;
use App\Exceptions\BookingLifecycleTransitionUnavailable;
use App\Models\AllocationOccupancy;
use App\Models\Booking;
use App\Models\EquipmentAllocation;
use App\Models\User;
use App\Services\CentreReservationLock;
use App\Services\CustomerBookingManagementPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CancelBooking
{
    public function __construct(
        private CentreReservationLock $centreReservationLock,
        private CustomerBookingManagementPolicy $managementPolicy,
    ) {}

    public function handle(User $actor, Booking $booking, ?string $reason = null): Booking
    {
        $reason = trim((string) $reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Tell us why you are cancelling this booking.']);
        }

        $this->authorize($actor, $booking);
        $centreId = $this->centreReservationLock->centreIdForBooking($booking->id);

        return DB::transaction(function () use ($actor, $booking, $centreId, $reason): Booking {
            $this->centreReservationLock->lock($centreId);
            $booking = Booking::query()->with('centre')->lockForUpdate()->findOrFail($booking->id);
            $this->authorize($actor, $booking);

            $unavailableReason = $this->managementPolicy->cancellationUnavailableReason(
                $booking,
                CarbonImmutable::now(config('app.timezone')),
            );

            if ($unavailableReason !== null) {
                throw new BookingLifecycleTransitionUnavailable($unavailableReason);
            }

            EquipmentAllocation::query()
                ->where('booking_id', $booking->id)
                ->lockForUpdate()
                ->get()
                ->each
                ->delete();

            $occupancy = AllocationOccupancy::query()
                ->where('booking_id', $booking->id)
                ->lockForUpdate()
                ->first();

            if ($occupancy !== null) {
                $occupancy->allocationUnits()->detach();
                $occupancy->delete();
            }

            $booking->update(['status' => BookingStatus::Cancelled]);
            activity('booking')
                ->performedOn($booking)
                ->causedBy($actor)
                ->event('booking.cancelled')
                ->withProperties([
                    'reason' => $reason,
                    'organisation_id' => $booking->organisation_id,
                    'actor_organisation_role' => $booking->organisation?->membershipFor($actor)?->role->value,
                    'financial_follow_up' => $this->managementPolicy->financialFollowUp($booking),
                    'financial_status_unchanged' => $booking->financial_status->value,
                ])
                ->log('Booking cancelled');

            return $booking->fresh() ?? $booking;
        });
    }

    private function authorize(User $actor, Booking $booking): void
    {
        if (! $actor->can('bookings.cancel')) {
            throw new AuthorizationException;
        }

        Gate::forUser($actor)->authorize('cancel', $booking);
    }
}
