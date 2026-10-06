<?php

namespace App\Actions;

use App\Exceptions\BookingSubmissionUnavailable;
use App\Models\Booking;
use App\Models\User;
use App\Services\BookingRequestEngine;
use App\Services\CentreReservationLock;
use App\Services\PricingContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateBookingRequest
{
    public function __construct(
        private BookingRequestEngine $bookingRequestEngine,
        private CentreReservationLock $centreReservationLock,
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
    ): Booking {
        $centreId = $this->centreReservationLock->centreIdForResource($resourceId);

        return DB::transaction(function () use ($centreId, $customer, $resourceId, $startsAt, $endsAt, $equipmentSelections, $pricingContext, $actor): Booking {
            $context = $this->bookingRequestEngine->lockContext($resourceId, $equipmentSelections, $centreId);
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
            );
        });
    }
}
