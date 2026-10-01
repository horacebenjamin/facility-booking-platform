<?php

namespace App\Services;

use App\Enums\EquipmentChargeType;
use App\Enums\PricingFailureReason;
use App\Models\Equipment;
use App\Models\EquipmentRate;
use App\Models\ResourceRate;
use Illuminate\Database\Eloquent\Collection;

class PricingService
{
    private const SecondsPerHour = 3600;

    public function quote(PricingRequest $request): PricingQuoteResult
    {
        if (! $request->startsAt->lt($request->endsAt) || ! $this->hasValidEquipmentRequirements($request)) {
            return $this->failure(PricingFailureReason::InvalidRequest);
        }

        $effectiveDate = $request->startsAt->toDateString();
        $resourceRates = $this->applicableResourceRates($request, $effectiveDate);

        if ($resourceRates->isEmpty()) {
            return $this->failure(PricingFailureReason::MissingResourceRate, $request->resource->name);
        }

        if ($resourceRates->count() > 1) {
            return $this->failure(PricingFailureReason::AmbiguousResourceRate, $request->resource->name);
        }

        $resourceRate = $resourceRates->sole();
        $durationSeconds = $request->endsAt->getTimestamp() - $request->startsAt->getTimestamp();
        $resourceAmount = $this->proportionalHourlyAmount($resourceRate->amount_minor, $durationSeconds);

        if ($resourceAmount === null) {
            return $this->failure(PricingFailureReason::InvalidRequest);
        }

        $equipmentLines = [];
        $equipmentAmount = 0;

        foreach ($request->equipmentRequirements as $equipmentRequirement) {
            $equipmentRates = $this->applicableEquipmentRates($equipmentRequirement->equipment, $effectiveDate);

            if ($equipmentRates->isEmpty()) {
                return $this->failure(PricingFailureReason::MissingEquipmentRate, $equipmentRequirement->equipment->name);
            }

            if ($equipmentRates->count() > 1) {
                return $this->failure(PricingFailureReason::AmbiguousEquipmentRate, $equipmentRequirement->equipment->name);
            }

            $equipmentRate = $equipmentRates->sole();

            if ($equipmentRate->currency !== $resourceRate->currency) {
                return $this->failure(PricingFailureReason::CurrencyMismatch, $equipmentRequirement->equipment->name);
            }

            $lineAmount = $equipmentRate->charge_type === EquipmentChargeType::Included
                ? 0
                : $this->proportionalHourlyAmount($equipmentRate->amount_minor, $durationSeconds, $equipmentRequirement->quantity);

            if ($lineAmount === null || $equipmentAmount > PHP_INT_MAX - $lineAmount) {
                return $this->failure(PricingFailureReason::InvalidRequest);
            }

            $equipmentAmount += $lineAmount;
            $equipmentLines[] = $this->equipmentLine($equipmentRequirement->equipment, $equipmentRequirement->quantity, $equipmentRate, $durationSeconds, $lineAmount);
        }

        if ($resourceAmount > PHP_INT_MAX - $equipmentAmount) {
            return $this->failure(PricingFailureReason::InvalidRequest);
        }

        $subtotal = $resourceAmount + $equipmentAmount;
        $discount = $this->discountLine($request->context?->discount, $subtotal);

        if ($discount === false) {
            return $this->failure(PricingFailureReason::InvalidRequest);
        }

        $calculatedTotal = $subtotal - ($discount->amountMinor ?? 0);
        $override = $request->context?->override;

        if ($override !== null) {
            $overrideFailure = $this->validateOverride($override, $request);

            if ($overrideFailure !== null) {
                return $overrideFailure;
            }

            $overrideDetails = new PriceOverrideDetails(
                $calculatedTotal,
                $override->adjustedTotalMinor,
                trim($override->reason),
                $override->actor->id,
                $override->actor->name,
                $override->adjustedAt,
            );
        } else {
            $overrideDetails = null;
        }

        return PricingQuoteResult::success(new PriceQuote(
            $resourceRate->currency,
            $this->resourceLine($request, $resourceRate, $durationSeconds, $resourceAmount),
            $equipmentLines,
            $resourceAmount,
            $equipmentAmount,
            $subtotal,
            $discount,
            $calculatedTotal,
            $overrideDetails,
            $overrideDetails->adjustedTotalMinor ?? $calculatedTotal,
        ));
    }

    /**
     * @return Collection<int, ResourceRate>
     */
    private function applicableResourceRates(PricingRequest $request, string $effectiveDate): Collection
    {
        return $request->resource->rates()
            ->where('effective_from', '<=', $effectiveDate)
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $effectiveDate))
            ->get();
    }

    /**
     * @return Collection<int, EquipmentRate>
     */
    private function applicableEquipmentRates(Equipment $equipment, string $effectiveDate): Collection
    {
        return $equipment->rates()
            ->where('effective_from', '<=', $effectiveDate)
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $effectiveDate))
            ->get();
    }

    private function hasValidEquipmentRequirements(PricingRequest $request): bool
    {
        foreach ($request->equipmentRequirements as $equipmentRequirement) {
            if ($equipmentRequirement->quantity <= 0) {
                return false;
            }
        }

        return true;
    }

    private function proportionalHourlyAmount(int $hourlyAmountMinor, int $durationSeconds, int $quantity = 1): ?int
    {
        if ($hourlyAmountMinor < 0 || $durationSeconds <= 0 || $quantity <= 0) {
            return null;
        }

        if ($hourlyAmountMinor > intdiv(PHP_INT_MAX, $durationSeconds)
            || $hourlyAmountMinor * $durationSeconds > intdiv(PHP_INT_MAX, $quantity)) {
            return null;
        }

        $numerator = $hourlyAmountMinor * $durationSeconds * $quantity;

        // Rates are hourly; fractions of a minor unit round to the nearest unit, with halves up.
        return intdiv($numerator + intdiv(self::SecondsPerHour, 2), self::SecondsPerHour);
    }

    private function discountLine(?PricingDiscount $discount, int $subtotal): PriceDiscountLine|false|null
    {
        if ($discount === null) {
            return null;
        }

        if ($discount->amountMinor < 0 || trim($discount->description) === '') {
            return false;
        }

        return new PriceDiscountLine(
            $discount->amountMinor,
            min($discount->amountMinor, $subtotal),
            trim($discount->description),
        );
    }

    private function validateOverride(PricingOverride $override, PricingRequest $request): ?PricingQuoteResult
    {
        if ($override->adjustedTotalMinor < 0 || trim($override->reason) === '') {
            return $this->failure(PricingFailureReason::InvalidOverride);
        }

        $centre = $request->resource->facility->centre;

        if (! $override->actor->can('pricing.manage') || ! $override->actor->isAssignedToCentre($centre)) {
            return $this->failure(PricingFailureReason::UnauthorizedOverride);
        }

        return null;
    }

    private function resourceLine(PricingRequest $request, ResourceRate $rate, int $durationSeconds, int $amountMinor): ResourcePriceLine
    {
        return new ResourcePriceLine(
            $request->resource->name,
            $rate->amount_minor,
            $rate->currency,
            $rate->rate_unit,
            $rate->effective_from,
            $rate->effective_until,
            $durationSeconds,
            $amountMinor,
        );
    }

    private function equipmentLine(Equipment $equipment, int $quantity, EquipmentRate $rate, int $durationSeconds, int $amountMinor): EquipmentPriceLine
    {
        return new EquipmentPriceLine(
            $equipment->name,
            $quantity,
            $rate->charge_type,
            $rate->amount_minor,
            $rate->currency,
            $rate->rate_unit,
            $rate->effective_from,
            $rate->effective_until,
            $durationSeconds,
            $amountMinor,
        );
    }

    private function failure(PricingFailureReason $reason, ?string $subjectName = null): PricingQuoteResult
    {
        return PricingQuoteResult::failure(new PricingFailure($reason, $subjectName));
    }
}
