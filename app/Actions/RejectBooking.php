<?php

namespace App\Actions;

use App\Enums\BookingStatus;
use App\Exceptions\BookingLifecycleTransitionUnavailable;
use App\Models\AllocationOccupancy;
use App\Models\Booking;
use App\Models\EquipmentAllocation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RejectBooking
{
    public function handle(User $actor, Booking $booking, string $reason): Booking
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A rejection reason is required.',
            ]);
        }

        $this->authorize($actor);
        Gate::forUser($actor)->authorize('reject', $booking);

        return DB::transaction(function () use ($actor, $booking, $reason): Booking {
            $booking = Booking::query()
                ->with('centre')
                ->lockForUpdate()
                ->findOrFail($booking->id);

            $this->authorize($actor);
            Gate::forUser($actor)->authorize('reject', $booking);

            if ($booking->status !== BookingStatus::Requested) {
                throw new BookingLifecycleTransitionUnavailable('This booking is no longer awaiting management approval.');
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

            $booking->update(['status' => BookingStatus::Rejected]);

            activity('booking')
                ->performedOn($booking)
                ->causedBy($actor)
                ->event('booking.rejected')
                ->withProperties(['reason' => $reason])
                ->log('Booking rejected');

            return $booking->fresh() ?? $booking;
        });
    }

    private function authorize(User $actor): void
    {
        if (! $actor->can('bookings.approve')) {
            throw new AuthorizationException;
        }
    }
}
