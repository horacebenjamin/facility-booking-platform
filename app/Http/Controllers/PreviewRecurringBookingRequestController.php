<?php

namespace App\Http\Controllers;

use App\Actions\PreviewRecurringBookingRequest;
use App\Enums\RecurrenceFrequency;
use App\Exceptions\BookingSubmissionUnavailable;
use App\Http\Requests\RecurringBookingPreviewRequest;
use App\Models\User;
use App\Services\OrganisationBookingContext;
use App\Services\RecurrencePattern;
use App\Services\RecurringBookingPayload;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class PreviewRecurringBookingRequestController extends Controller
{
    public function __invoke(
        RecurringBookingPreviewRequest $request,
        PreviewRecurringBookingRequest $previewRecurringBookingRequest,
        RecurringBookingPayload $payload,
        OrganisationBookingContext $organisationContext,
    ): JsonResponse {
        /** @var array{resource_id: int, starts_at: string, ends_at: string, interval_weeks: int, occurrence_count: int, timezone: string, equipment?: list<array{equipment_id: int, quantity: int}>, organisation_id?: int|null} $validated */
        $validated = $request->validated();
        $pattern = $this->pattern($validated);
        /** @var User $customer */
        $customer = $request->user();
        $organisation = $organisationContext->resolveBookable($customer, $validated['organisation_id'] ?? null);

        try {
            $validation = $previewRecurringBookingRequest->handle(
                $customer,
                $validated['resource_id'],
                $this->parseDateTime($validated['starts_at'], $validated['timezone']),
                $this->parseDateTime($validated['ends_at'], $validated['timezone']),
                $pattern,
                $validated['equipment'] ?? [],
                organisation: $organisation,
            );
        } catch (BookingSubmissionUnavailable) {
            return response()->json([
                'message' => 'This recurring booking selection could not be validated.',
            ], 409);
        }

        return response()->json([
            'data' => $payload->validation($validation, $pattern, $validated['equipment'] ?? []),
        ]);
    }

    /**
     * @param  array{interval_weeks: int, occurrence_count: int, timezone: string}  $validated
     */
    private function pattern(array $validated): RecurrencePattern
    {
        return new RecurrencePattern(
            RecurrenceFrequency::Weekly,
            $validated['interval_weeks'],
            $validated['occurrence_count'],
            $validated['timezone'],
        );
    }

    private function parseDateTime(string $dateTime, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, $timezone)->setTimezone(config('app.timezone'));
    }
}
