<?php

namespace App\Actions;

use App\Models\BookingSeries;
use App\Models\User;
use App\Services\BookingRequestEngine;
use App\Services\BookingSeriesValidator;
use App\Services\CreateBookingSeriesResult;
use App\Services\RecurrenceGenerator;
use App\Services\RecurrencePattern;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateRecurringBookingRequest
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
    ): CreateBookingSeriesResult {
        $this->authorize($customer);
        $periods = $this->recurrenceGenerator->generate($firstStartsAt, $firstEndsAt, $pattern);

        return DB::transaction(function () use ($customer, $resourceId, $pattern, $equipmentSelections, $periods): CreateBookingSeriesResult {
            $context = $this->bookingRequestEngine->lockContext($resourceId, $equipmentSelections);
            $evaluatedAt = CarbonImmutable::now(config('app.timezone'));
            $validation = $this->bookingSeriesValidator->validate($context, $periods, $evaluatedAt);

            if (! $validation->isValid()) {
                return new CreateBookingSeriesResult($validation);
            }

            $firstPeriod = $periods[0];
            $series = BookingSeries::query()->create([
                'identifier' => (string) Str::uuid(),
                'customer_id' => $customer->id,
                'centre_id' => $context->resource->facility->centre_id,
                'facility_id' => $context->resource->facility_id,
                'resource_id' => $context->resource->id,
                'recurrence_frequency' => $pattern->frequency,
                'interval_weeks' => $pattern->intervalWeeks,
                'occurrence_count' => $pattern->occurrenceCount,
                'timezone' => $pattern->timezone,
                'first_starts_at' => $firstPeriod->startsAt,
                'first_ends_at' => $firstPeriod->endsAt,
            ]);
            $bookings = [];

            foreach ($validation->validOccurrences as $occurrence) {
                $bookings[] = $this->bookingRequestEngine->persist(
                    customer: $customer,
                    context: $context,
                    startsAt: $occurrence->period->startsAt,
                    endsAt: $occurrence->period->endsAt,
                    equipmentSelections: $equipmentSelections,
                    validation: $occurrence->bookingValidation,
                    evaluatedAt: $evaluatedAt,
                    series: $series,
                    occurrenceIndex: $occurrence->period->index,
                );
            }

            return new CreateBookingSeriesResult($validation, $series, $bookings);
        });
    }

    private function authorize(User $customer): void
    {
        if (! $customer->can('bookings.create')) {
            throw new AuthorizationException;
        }
    }
}
