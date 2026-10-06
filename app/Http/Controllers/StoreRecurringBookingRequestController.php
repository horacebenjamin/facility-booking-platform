<?php

namespace App\Http\Controllers;

use App\Actions\CreateRecurringBookingRequest;
use App\Enums\RecurrenceFrequency;
use App\Exceptions\BookingSubmissionUnavailable;
use App\Http\Requests\StoreRecurringBookingRequest;
use App\Models\User;
use App\Services\OrganisationBookingContext;
use App\Services\RecurrencePattern;
use App\Services\RecurringBookingPayload;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class StoreRecurringBookingRequestController extends Controller
{
    public function __invoke(
        StoreRecurringBookingRequest $request,
        CreateRecurringBookingRequest $createRecurringBookingRequest,
        RecurringBookingPayload $payload,
        OrganisationBookingContext $organisationContext,
    ): JsonResponse {
        /** @var array{resource_id: int, starts_at: string, ends_at: string, interval_weeks: int, occurrence_count: int, timezone: string, equipment?: list<array{equipment_id: int, quantity: int}>, submission_mode: string, selected_occurrence_indexes?: list<int>, organisation_id?: int|null} $validated */
        $validated = $request->validated();
        $pattern = new RecurrencePattern(
            RecurrenceFrequency::Weekly,
            $validated['interval_weeks'],
            $validated['occurrence_count'],
            $validated['timezone'],
        );
        /** @var User $customer */
        $customer = $request->user();
        $organisation = $organisationContext->resolveBookable($customer, $validated['organisation_id'] ?? null);
        $selectedOccurrenceIndexes = $validated['submission_mode'] === 'available_occurrences'
            ? ($validated['selected_occurrence_indexes'] ?? [])
            : null;
        try {
            $result = $createRecurringBookingRequest->handle(
                $customer,
                $validated['resource_id'],
                $this->parseDateTime($validated['starts_at'], $validated['timezone']),
                $this->parseDateTime($validated['ends_at'], $validated['timezone']),
                $pattern,
                $validated['equipment'] ?? [],
                $selectedOccurrenceIndexes,
                organisation: $organisation,
            );
        } catch (BookingSubmissionUnavailable) {
            return response()->json([
                'message' => 'This recurring booking selection is no longer available.',
            ], 409);
        }

        if (! $result->wasCreated()) {
            return response()->json([
                'message' => 'One or more selected occurrences are no longer available.',
                'data' => $payload->validation($result->validation, $pattern, $validated['equipment'] ?? []),
            ], 409);
        }

        $series = $result->series;

        if ($series === null) {
            return response()->json([
                'message' => 'This recurring booking selection is no longer available.',
            ], 409);
        }

        return response()->json([
            'data' => $payload->confirmation($series, $result->bookings),
        ], 201);
    }

    private function parseDateTime(string $dateTime, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, $timezone)->setTimezone(config('app.timezone'));
    }
}
