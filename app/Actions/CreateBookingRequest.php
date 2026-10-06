<?php

namespace App\Actions;

use App\Exceptions\BookingSubmissionUnavailable;
use App\Models\Booking;
use App\Models\Organisation;
use App\Models\User;
use App\Services\BookingRequestEngine;
use App\Services\CentreReservationLock;
use App\Services\OrganisationBookingContext;
use App\Services\PricingContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateBookingRequest
{
    public function __construct(
        private BookingRequestEngine $bookingRequestEngine,
        private CentreReservationLock $centreReservationLock,
        private OrganisationBookingContext $organisationBookingContext,
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
        ?PricingContext $pricingContext = null,
        ?User $actor = null,
        ?Organisation $organisation = null,
    ): Booking {
        if ($organisation !== null) {
            Gate::forUser($customer)->authorize('createBooking', $organisation);
        }

        $centreId = $this->centreReservationLock->centreIdForResource($resourceId);

        return DB::transaction(function () use ($centreId, $customer, $resourceId, $startsAt, $endsAt, $equipmentSelections, $pricingContext, $actor, $organisation): Booking {
            $context = $this->bookingRequestEngine->lockContext($resourceId, $equipmentSelections, $centreId);

            if ($organisation !== null) {
                $organisation = $this->organisationBookingContext->lockForBooking($customer, $organisation);
            }

            $evaluatedAt = CarbonImmutable::now(config('app.timezone'));
            $validation = $this->bookingRequestEngine->validate(
                $context,
                $startsAt,
                $endsAt,
                $evaluatedAt,
                pricingContext: $pricingContext,
            );

            if (! $validation->canCreate()) {
                throw new BookingSubmissionUnavailable;
            }

            return $this->bookingRequestEngine->persist(
                customer: $customer,
                context: $context,
                startsAt: $startsAt,
                endsAt: $endsAt,
                equipmentSelections: $equipmentSelections,
                validation: $validation,
                evaluatedAt: $evaluatedAt,
                actor: $actor,
                organisation: $organisation,
            );
        });
    }
}
