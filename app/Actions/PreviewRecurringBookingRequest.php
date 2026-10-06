<?php

namespace App\Actions;

use App\Models\Organisation;
use App\Models\User;
use App\Services\BookingRequestEngine;
use App\Services\BookingSeriesValidationResult;
use App\Services\BookingSeriesValidator;
use App\Services\RecurrenceGenerator;
use App\Services\RecurrencePattern;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class PreviewRecurringBookingRequest
{
    public function __construct(
        private RecurrenceGenerator $recurrenceGenerator,
        private BookingRequestEngine $bookingRequestEngine,
        private BookingSeriesValidator $bookingSeriesValidator,
    ) {}

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipmentSelections
     */
    public function handle(
        User $customer,
        int $resourceId,
        CarbonImmutable $firstStartsAt,
        CarbonImmutable $firstEndsAt,
        RecurrencePattern $pattern,
        array $equipmentSelections = [],
        ?Organisation $organisation = null,
    ): BookingSeriesValidationResult {
        if (! $customer->can('bookings.create')) {
            throw new AuthorizationException;
        }

        if ($organisation !== null) {
            Gate::forUser($customer)->authorize('createBooking', $organisation);
        }

        $periods = $this->recurrenceGenerator->generate($firstStartsAt, $firstEndsAt, $pattern);
        $context = $this->bookingRequestEngine->context($resourceId, $equipmentSelections);

        return $this->bookingSeriesValidator->validate(
            $context,
            $periods,
            CarbonImmutable::now(config('app.timezone')),
        );
    }
}
