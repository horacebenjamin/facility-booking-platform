<?php

namespace App\Http\Controllers;

use App\Actions\CreateBookingRequest;
use App\Exceptions\BookingSubmissionUnavailable;
use App\Http\Requests\StoreBookingRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class StoreBookingRequestController extends Controller
{
    public function __invoke(StoreBookingRequest $request, CreateBookingRequest $createBookingRequest): JsonResponse
    {
        /** @var array{resource_id: int, starts_at: string, ends_at: string, equipment?: list<array{equipment_id: int, quantity: int}>} $validated */
        $validated = $request->validated();
        /** @var User $customer */
        $customer = $request->user();

        try {
            $booking = $createBookingRequest->handle(
                $customer,
                $validated['resource_id'],
                $this->parseDateTime($validated['starts_at']),
                $this->parseDateTime($validated['ends_at']),
                $validated['equipment'] ?? [],
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
            ],
        ], 201);
    }

    private function parseDateTime(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, config('app.timezone'))
            ->setTimezone(config('app.timezone'));
    }
}
