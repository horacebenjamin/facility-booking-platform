<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowBookingReviewRequest;
use App\Models\Equipment;
use App\Models\Resource;
use App\Models\User;
use App\Services\EquipmentRequirement;
use App\Services\PricingRequest;
use App\Services\PricingService;
use App\Services\RecurrencePattern;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BookingReviewController extends Controller
{
    public function __invoke(ShowBookingReviewRequest $request, PricingService $pricingService): Response|RedirectResponse
    {
        /** @var array{resource_id: int, starts_at: string, ends_at: string, equipment?: list<array{equipment_id: int, quantity: int}>} $validated */
        $validated = $request->validated();
        $resource = Resource::query()
            ->with('facility.centre')
            ->findOrFail($validated['resource_id']);
        $equipmentSelections = $validated['equipment'] ?? [];
        $equipmentById = Equipment::query()
            ->whereKey(collect($equipmentSelections)->pluck('equipment_id'))
            ->get()
            ->keyBy('id');
        $requirements = [];
        $equipmentSummary = [];

        foreach ($equipmentSelections as $selection) {
            /** @var Equipment $equipment */
            $equipment = $equipmentById->get($selection['equipment_id']);
            $requirements[] = new EquipmentRequirement(
                $equipment,
                $selection['quantity'],
            );
            $equipmentSummary[] = [
                'equipment_id' => $equipment->id,
                'name' => $equipment->name,
                'quantity' => (int) $selection['quantity'],
            ];
        }
        $startsAt = $this->parseDateTime($validated['starts_at']);
        $endsAt = $this->parseDateTime($validated['ends_at']);
        $pricing = $pricingService->quote(new PricingRequest(
            $resource,
            $startsAt,
            $endsAt,
            $requirements,
        ));

        if ($pricing->quote === null) {
            return to_route('availability.index', $validated)->with(
                'bookingReviewError',
                'Pricing changed before review. Please check availability again.',
            );
        }

        /** @var User $customer */
        $customer = $request->user();

        return Inertia::render('bookings/Review', [
            'selection' => [
                'resource_id' => $resource->id,
                'centre_name' => $resource->facility->centre->name,
                'facility_name' => $resource->facility->name,
                'resource_name' => $resource->name,
                'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                'ends_at' => $endsAt->format('Y-m-d H:i:s'),
                'duration_seconds' => $endsAt->getTimestamp() - $startsAt->getTimestamp(),
                'equipment' => $equipmentSummary,
            ],
            'quote' => [
                'currency' => $pricing->quote->currency,
                'total_minor' => $pricing->quote->finalTotalMinor,
            ],
            'customer' => [
                'name' => $customer->name,
                'email' => $customer->email,
            ],
            'recurrence' => [
                'timezone' => config('booking.recurrence_timezone'),
                'minimum_interval_weeks' => 1,
                'maximum_interval_weeks' => RecurrencePattern::MaximumIntervalWeeks,
                'minimum_occurrences' => 2,
                'maximum_occurrences' => RecurrencePattern::MaximumOccurrences,
            ],
        ]);
    }

    private function parseDateTime(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, config('app.timezone'))
            ->setTimezone(config('app.timezone'));
    }
}
