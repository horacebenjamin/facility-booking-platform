<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class EquipmentAvailabilityEvaluator
{
    public function isAvailable(
        Equipment $equipment,
        int $requestedQuantity,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?CarbonInterface $evaluatedAt = null,
        ?int $excludedBookingId = null,
        bool $lockReservations = false,
    ): bool {
        $requestedPeriod = $this->normaliseRequestedPeriod($startsAt, $endsAt);

        if ($requestedQuantity <= 0 || $requestedPeriod === null || ! $equipment->is_active || $requestedQuantity > $equipment->quantity) {
            return false;
        }

        $evaluatedAt = CarbonImmutable::instance($evaluatedAt ?? now())
            ->setTimezone(config('app.timezone'));

        $allocatedQuantity = EquipmentAllocation::query()
            ->whereBelongsTo($equipment)
            ->where('starts_at', '<', $requestedPeriod['endsAt'])
            ->where('ends_at', '>', $requestedPeriod['startsAt'])
            ->where(function (Builder $query) use ($evaluatedAt): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $evaluatedAt);
            });

        if ($excludedBookingId !== null) {
            $allocatedQuantity->where(function (Builder $query) use ($excludedBookingId): void {
                $query
                    ->whereNull('booking_id')
                    ->orWhere('booking_id', '!=', $excludedBookingId);
            });
        }

        $allocatedQuantity = $lockReservations
            ? $allocatedQuantity->orderBy('id')->lockForUpdate()->get(['id', 'quantity'])->sum('quantity')
            : $allocatedQuantity->sum('quantity');

        return $allocatedQuantity + $requestedQuantity <= $equipment->quantity;
    }

    /**
     * @return array{startsAt: CarbonImmutable, endsAt: CarbonImmutable}|null
     */
    private function normaliseRequestedPeriod(CarbonInterface $startsAt, CarbonInterface $endsAt): ?array
    {
        $startsAt = CarbonImmutable::instance($startsAt)->setTimezone(config('app.timezone'));
        $endsAt = CarbonImmutable::instance($endsAt)->setTimezone(config('app.timezone'));

        if (! $startsAt->lt($endsAt)) {
            return null;
        }

        return [
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
        ];
    }
}
