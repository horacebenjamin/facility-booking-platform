<?php

namespace App\Services;

use App\Enums\BookingPriceLineType;
use App\Enums\BookingStatus;
use App\Enums\FinancialStatus;
use App\Exceptions\PaymentUnavailable;
use App\Models\Booking;
use App\Models\BookingPriceSnapshot;
use Carbon\CarbonInterface;

class BookingPaymentEligibility
{
    public function __construct(private OperationalOccupancyCalculator $occupancyCalculator) {}

    public function assertLifecycle(Booking $booking, CarbonInterface $evaluatedAt): void
    {
        if ($booking->status !== BookingStatus::Approved
            || $booking->financial_status !== FinancialStatus::AwaitingPayment
            || $booking->payment_due_at === null
            || $booking->starts_at->lte($evaluatedAt)) {
            throw new PaymentUnavailable('This booking is not eligible for card payment.');
        }
    }

    public function snapshot(Booking $booking): BookingPriceSnapshot
    {
        $snapshot = $booking->priceSnapshot()->with('lines')->first();

        if ($snapshot === null
            || ! in_array($snapshot->currency, config('payments.currencies'), true)
            || $snapshot->final_total_minor < 1
            || $snapshot->final_total_minor > 99999999
            || $snapshot->subtotal_minor !== $snapshot->resource_amount_minor + $snapshot->equipment_amount_minor
            || $snapshot->discount_amount_minor > $snapshot->subtotal_minor
            || $snapshot->calculated_total_minor !== $snapshot->subtotal_minor - $snapshot->discount_amount_minor
            || $snapshot->final_total_minor !== ($snapshot->override_adjusted_total_minor ?? $snapshot->calculated_total_minor)
            || $snapshot->lines->where('line_type', BookingPriceLineType::Resource)->count() !== 1
            || $snapshot->lines->where('line_type', BookingPriceLineType::Resource)->sum('amount_minor') !== $snapshot->resource_amount_minor
            || $snapshot->lines->where('line_type', BookingPriceLineType::Equipment)->sum('amount_minor') !== $snapshot->equipment_amount_minor
            || $snapshot->lines->contains(fn ($line): bool => $line->booking_id !== $booking->id)) {
            throw new PaymentUnavailable('The agreed booking price is unavailable. Please contact the centre.');
        }

        $equipment = $booking->equipmentRequests()->get();
        $equipmentLines = $snapshot->lines->where('line_type', BookingPriceLineType::Equipment);

        if ($equipmentLines->count() !== $equipment->count()
            || ! $equipment->every(fn ($request): bool => $equipmentLines->contains(
                fn ($line): bool => $line->booking_equipment_id === $request->id && $line->quantity === $request->requested_quantity,
            ))) {
            throw new PaymentUnavailable('The agreed equipment price is unavailable. Please contact the centre.');
        }

        if ($snapshot->override_adjusted_total_minor !== null
            && ($snapshot->override_original_total_minor !== $snapshot->calculated_total_minor
                || trim($snapshot->override_reason ?? '') === ''
                || $snapshot->override_responsible_user_id === null
                || $snapshot->override_adjusted_at === null)) {
            throw new PaymentUnavailable('The agreed booking price requires review.');
        }

        return $snapshot;
    }

    public function assertProtection(Booking $booking): void
    {
        $resource = $booking->resource;
        $period = $this->occupancyCalculator->calculate($resource, $booking->starts_at, $booking->ends_at);
        $occupancy = $booking->allocationOccupancy()->first();
        $units = $resource->allocationUnits()->orderBy('allocation_units.id')->pluck('allocation_units.id')->all();

        if ($period === null || $occupancy === null || $occupancy->expires_at !== null
            || $units === []
            || ! $occupancy->starts_at->equalTo($period->startsAt)
            || ! $occupancy->ends_at->equalTo($period->endsAt)
            || $occupancy->allocationUnits()->orderBy('allocation_units.id')->pluck('allocation_units.id')->all() !== $units) {
            throw new PaymentUnavailable('This booking requires a protection review. Please contact the centre.');
        }

        $requests = $booking->equipmentRequests()->get();
        $allocations = $booking->equipmentAllocations()->get()->keyBy('booking_equipment_id');

        if ($requests->count() !== $allocations->count()
            || ! $requests->every(function ($request) use ($booking, $allocations): bool {
                $allocation = $allocations->get($request->id);

                return $allocation !== null
                    && $allocation->expires_at === null
                    && $allocation->equipment_id === $request->equipment_id
                    && $allocation->quantity === $request->requested_quantity
                    && $allocation->starts_at->equalTo($booking->starts_at)
                    && $allocation->ends_at->equalTo($booking->ends_at);
            })) {
            throw new PaymentUnavailable('This booking requires an equipment protection review.');
        }
    }
}
