<?php

namespace App\Http\Controllers;

use App\Enums\PricingFailureReason;
use App\Http\Requests\QuotePricingRequest;
use App\Models\Equipment;
use App\Models\Resource;
use App\Services\BookingDateTime;
use App\Services\EquipmentPriceLine;
use App\Services\EquipmentRequirement;
use App\Services\PriceQuote;
use App\Services\PricingRequest;
use App\Services\PricingService;
use App\Services\ResourcePriceLine;
use Illuminate\Http\JsonResponse;

class QuotePricingController extends Controller
{
    public function __invoke(QuotePricingRequest $request, PricingService $pricingService): JsonResponse
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

        $result = $pricingService->quote(new PricingRequest(
            $resource,
            BookingDateTime::fromLocalInput($validated['starts_at']),
            BookingDateTime::fromLocalInput($validated['ends_at']),
            $equipmentRequirements,
        ));

        $quote = $result->quote;

        if ($quote === null) {
            return $this->pricingFailureResponse(
                $result->failure->reason ?? PricingFailureReason::InvalidRequest,
            );
        }

        return response()->json(['data' => $this->quotePayload($quote)]);
    }

    private function pricingFailureResponse(PricingFailureReason $reason): JsonResponse
    {
        if ($reason === PricingFailureReason::InvalidRequest) {
            return response()->json([
                'message' => 'We could not calculate a price for this selection.',
            ], 422);
        }

        return response()->json([
            'message' => 'Pricing is not available for this selection.',
        ], 409);
    }

    /**
     * @return array{
     *     currency: string,
     *     duration_seconds: int,
     *     resource: array{name: string, hourly_rate_minor: int, rate_unit: string, amount_minor: int},
     *     equipment: list<array{name: string, quantity: int, charge_type: string, hourly_rate_minor: int, rate_unit: string, amount_minor: int}>,
     *     subtotal_minor: int,
     *     discount_minor: int|null,
     *     calculated_total_minor: int,
     *     final_total_minor: int
     * }
     */
    private function quotePayload(PriceQuote $quote): array
    {
        return [
            'currency' => $quote->currency,
            'duration_seconds' => $quote->resourceLine->durationSeconds,
            'resource' => $this->resourceLinePayload($quote->resourceLine),
            'equipment' => array_map(
                fn (EquipmentPriceLine $line): array => $this->equipmentLinePayload($line),
                $quote->equipmentLines,
            ),
            'subtotal_minor' => $quote->subtotalMinor,
            'discount_minor' => $quote->discount?->amountMinor,
            'calculated_total_minor' => $quote->calculatedTotalMinor,
            'final_total_minor' => $quote->finalTotalMinor,
        ];
    }

    /**
     * @return array{name: string, hourly_rate_minor: int, rate_unit: string, amount_minor: int}
     */
    private function resourceLinePayload(ResourcePriceLine $line): array
    {
        return [
            'name' => $line->resourceName,
            'hourly_rate_minor' => $line->hourlyRateMinor,
            'rate_unit' => $line->rateUnit->value,
            'amount_minor' => $line->amountMinor,
        ];
    }

    /**
     * @return array{name: string, quantity: int, charge_type: string, hourly_rate_minor: int, rate_unit: string, amount_minor: int}
     */
    private function equipmentLinePayload(EquipmentPriceLine $line): array
    {
        return [
            'name' => $line->equipmentName,
            'quantity' => $line->quantity,
            'charge_type' => $line->chargeType->value,
            'hourly_rate_minor' => $line->hourlyRateMinor,
            'rate_unit' => $line->rateUnit->value,
            'amount_minor' => $line->amountMinor,
        ];
    }
}
