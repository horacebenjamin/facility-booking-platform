<?php

namespace App\Actions;

use App\Models\BookingSeries;
use App\Models\User;
use App\Services\BookingRequestEngine;
use App\Services\BookingSeriesValidator;
use App\Services\CentreReservationLock;
use App\Services\CreateBookingSeriesResult;
use App\Services\RecurrenceGenerator;
use App\Services\RecurrencePattern;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateRecurringBookingRequest
{
    public function __construct(
        private RecurrenceGenerator $recurrenceGenerator,
        private BookingRequestEngine $bookingRequestEngine,
        private BookingSeriesValidator $bookingSeriesValidator,
        private CentreReservationLock $centreReservationLock,
    ) {}

    /**
     * @param  list<array{equipment_id: int, quantity: int}>  $equipmentSelections
     * @param  list<int>|null  $selectedOccurrenceIndexes
     */
    public function handle(
        User $customer,
        int $resourceId,
        CarbonImmutable $firstStartsAt,
        CarbonImmutable $firstEndsAt,
        RecurrencePattern $pattern,
        array $equipmentSelections = [],
        ?array $selectedOccurrenceIndexes = null,
    ): CreateBookingSeriesResult {
        $this->authorize($customer);
        $periods = $this->recurrenceGenerator->generate($firstStartsAt, $firstEndsAt, $pattern);
        $this->validateSelectedOccurrenceIndexes($selectedOccurrenceIndexes, $pattern->occurrenceCount);

        $centreId = $this->centreReservationLock->centreIdForResource($resourceId);

        return DB::transaction(function () use ($centreId, $customer, $resourceId, $pattern, $equipmentSelections, $periods, $selectedOccurrenceIndexes): CreateBookingSeriesResult {
            $context = $this->bookingRequestEngine->lockContext($resourceId, $equipmentSelections, $centreId);
            $evaluatedAt = CarbonImmutable::now(config('app.timezone'));
            $validation = $this->bookingSeriesValidator->validate($context, $periods, $evaluatedAt);
            $occurrencesToPersist = $validation->validOccurrences;

            if ($selectedOccurrenceIndexes === null) {
                if (! $validation->isValid()) {
                    return new CreateBookingSeriesResult($validation);
                }
            } else {
                $selectedIndexLookup = array_fill_keys($selectedOccurrenceIndexes, true);
                $occurrencesToPersist = array_values(array_filter(
                    $validation->validOccurrences,
                    static fn ($occurrence): bool => isset($selectedIndexLookup[$occurrence->period->index]),
                ));

                if (count($occurrencesToPersist) !== count($selectedOccurrenceIndexes)) {
                    return new CreateBookingSeriesResult($validation);
                }
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

            foreach ($occurrencesToPersist as $occurrence) {
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

    /**
     * @param  list<int>|null  $selectedOccurrenceIndexes
     */
    private function validateSelectedOccurrenceIndexes(?array $selectedOccurrenceIndexes, int $occurrenceCount): void
    {
        if ($selectedOccurrenceIndexes === null) {
            return;
        }

        if ($selectedOccurrenceIndexes === []
            || count($selectedOccurrenceIndexes) !== count(array_unique($selectedOccurrenceIndexes))) {
            throw new InvalidArgumentException('Selected recurring occurrences must be non-empty and unique.');
        }

        foreach ($selectedOccurrenceIndexes as $index) {
            if ($index < 1 || $index > $occurrenceCount) {
                throw new InvalidArgumentException('A selected recurring occurrence is outside the generated series.');
            }
        }
    }
}
