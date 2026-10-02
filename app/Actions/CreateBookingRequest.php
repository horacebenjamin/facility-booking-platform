<?php

namespace App\Actions;

use App\Exceptions\BookingSubmissionUnavailable;
use App\Models\Booking;
use App\Models\User;
use App\Services\BookingRequestEngine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateBookingRequest
{
    public function __construct(private BookingRequestEngine $bookingRequestEngine) {}

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
            $context = $this->bookingRequestEngine->lockContext($resourceId, $equipmentSelections);
            $evaluatedAt = CarbonImmutable::now(config('app.timezone'));
            $validation = $this->bookingRequestEngine->validate($context, $startsAt, $endsAt, $evaluatedAt);

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
            );
        });
    }
}
