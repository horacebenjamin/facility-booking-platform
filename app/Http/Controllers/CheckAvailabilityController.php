<?php

namespace App\Http\Controllers;

use App\Enums\AvailabilityReason;
use App\Http\Requests\CheckAvailabilityRequest;
use App\Models\Equipment;
use App\Models\Resource;
use App\Services\AvailabilityService;
use App\Services\EquipmentRequirement;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class CheckAvailabilityController extends Controller
{
    public function __invoke(CheckAvailabilityRequest $request, AvailabilityService $availabilityService): JsonResponse
    {
        /** @var array{resource_id: int, starts_at: string, ends_at: string, equipment?: list<array{equipment_id: int, quantity: int}>} $validated */
        $validated = $request->validated();
        $resource = Resource::query()->with('facility.centre')->findOrFail($validated['resource_id']);
        $equipmentRequirements = [];

        foreach ($validated['equipment'] ?? [] as $equipment) {
            $equipmentRequirements[] = new EquipmentRequirement(
                Equipment::query()->findOrFail($equipment['equipment_id']),
                $equipment['quantity'],
            );
        }

        $availability = $availabilityService->check(
            $resource,
            $this->parseDateTime($validated['starts_at']),
            $this->parseDateTime($validated['ends_at']),
            $equipmentRequirements,
        );

        return response()->json([
            'data' => [
                'available' => $availability->isAvailable(),
                'reasons' => array_map(
                    fn (AvailabilityReason $reason): string => $reason->value,
                    $availability->reasons(),
                ),
            ],
        ]);
    }

    private function parseDateTime(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, config('app.timezone'))
            ->setTimezone(config('app.timezone'));
    }
}
