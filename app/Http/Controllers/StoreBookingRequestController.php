<?php

namespace App\Http\Controllers;

use App\Actions\CreateBookingRequest;
use App\Exceptions\BookingSubmissionUnavailable;
use App\Http\Requests\StoreBookingRequest;
use App\Models\User;
use App\Services\OrganisationBookingContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class StoreBookingRequestController extends Controller
{
    public function __invoke(
        StoreBookingRequest $request,
        CreateBookingRequest $createBookingRequest,
        OrganisationBookingContext $organisationContext,
    ): JsonResponse {
        /** @var array{resource_id: int, starts_at: string, ends_at: string, equipment?: list<array{equipment_id: int, quantity: int}>, organisation_id?: int|null} $validated */
        $validated = $request->validated();
        /** @var User $customer */
        $customer = $request->user();
        $organisation = $organisationContext->resolveBookable($customer, $validated['organisation_id'] ?? null);

        try {
            $booking = $createBookingRequest->handle(
                $customer,
                $validated['resource_id'],
                $this->parseDateTime($validated['starts_at']),
                $this->parseDateTime($validated['ends_at']),
                $validated['equipment'] ?? [],
                organisation: $organisation,
            );
        } catch (BookingSubmissionUnavailable) {
            return response()->json([
                'message' => 'This booking selection is no longer available.',
            ], 409);
        }

        return response()->json([
            'data' => [
                'reference' => $booking->reference,
                'status' => $booking->status->value,
                'status_label' => $booking->status->label(),
                'organisation_name' => $booking->organisation?->name,
                'booked_by_name' => $customer->name,
            ],
        ], 201);
    }

    private function parseDateTime(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, config('app.timezone'))
            ->setTimezone(config('app.timezone'));
    }
}
