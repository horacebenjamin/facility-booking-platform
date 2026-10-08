<?php

namespace App\Services;

use App\Enums\AvailabilityBlockScope;
use App\Enums\BookingStatus;
use App\Enums\ClosureImpactStatus;
use App\Models\AvailabilityBlock;
use App\Models\AvailabilityBlockBookingImpact;
use App\Models\Booking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

class ClosureImpactService
{
    public function __construct(
        private AvailabilityBlockScopeMatcher $scopes,
        private OperationalOccupancyCalculator $occupancy,
    ) {}

    public function detect(User $actor, AvailabilityBlock $block, CarbonImmutable $evaluatedAt): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Closure detection requires the caller to hold the centre reservation lock in a transaction.');
        }
        if ($block->ended_at !== null) {
            return 0;
        }
        $resources = $this->scopes->resourcesFor($block, lockReservations: true)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($resources->isEmpty()) {
            return 0;
        }
        $centreId = $block->owningCentre()->id;
        $earliestEnd = CarbonImmutable::instance($block->starts_at)->subMinutes((int) $resources->max('cleanup_minutes'));
        $latestStart = CarbonImmutable::instance($block->ends_at)->addMinutes((int) $resources->max('setup_minutes'));
        $unitIds = $block->blockScope() === AvailabilityBlockScope::Resource
            ? $block->resource->allocationUnits()->orderBy('allocation_units.id')->lockForUpdate()->pluck('allocation_units.id')->all() : [];
        $query = Booking::query()->select('bookings.*')
            ->leftJoin('allocation_occupancies as protection', 'protection.booking_id', '=', 'bookings.id')
            ->where('bookings.centre_id', $centreId);
        if ($block->blockScope() === AvailabilityBlockScope::Resource) {
            $query->leftJoin('allocation_occupancy_allocation_unit as physical', 'physical.allocation_occupancy_id', '=', 'protection.id');
        }
        $bookings = $query->where(function (Builder $query) use ($block, $resources, $unitIds): void {
            $query->whereIn('bookings.resource_id', $resources->modelKeys());
            if ($block->blockScope() === AvailabilityBlockScope::Resource) {
                $query->orWhereIn('physical.allocation_unit_id', $unitIds);
            }
        })->where(function (Builder $query) use ($block, $earliestEnd, $latestStart): void {
            $query->where(fn (Builder $query) => $query->where('protection.starts_at', '<', $block->ends_at)->where('protection.ends_at', '>', $block->starts_at))
                ->orWhere(fn (Builder $query) => $query->whereNull('protection.id')->where('bookings.starts_at', '<', $latestStart)->where('bookings.ends_at', '>', $earliestEnd));
        })->where(function (Builder $query) use ($evaluatedAt): void {
            $query->whereIn('bookings.status', [BookingStatus::Approved, BookingStatus::Confirmed])
                ->orWhere(fn (Builder $query) => $query->where('bookings.status', BookingStatus::Requested)->where('protection.expires_at', '>', $evaluatedAt));
        })->orderBy('bookings.id')->lockForUpdate()->get()->unique('id');
        $existing = AvailabilityBlockBookingImpact::query()->where('availability_block_id', $block->id)
            ->orderBy('id')->lockForUpdate()->pluck('booking_id')->all();
        $created = 0;
        foreach ($bookings as $booking) {
            $resource = $resources->get($booking->resource_id);
            if (in_array($booking->id, $existing, true)) {
                continue;
            }
            $reservation = $booking->allocationOccupancy()->lockForUpdate()->first();
            if ($booking->status === BookingStatus::Requested
                && ($reservation?->expires_at === null || $reservation->expires_at->lte($evaluatedAt))) {
                continue;
            }
            if ($reservation !== null) {
                $reservation->setRelation('allocationUnits', $reservation->allocationUnits()->orderBy('allocation_units.id')->lockForUpdate()->get());
            }
            if ($resource === null && ($reservation === null || $block->blockScope() !== AvailabilityBlockScope::Resource
                || $reservation->allocationUnits->pluck('id')->intersect($unitIds)->isEmpty())) {
                continue;
            }
            if ($reservation !== null && $block->blockScope() === AvailabilityBlockScope::Resource
                && $booking->resource_id !== $block->resource_id
                && $reservation->allocationUnits->pluck('id')->intersect($unitIds)->isEmpty()) {
                continue;
            }
            $period = $reservation !== null
                ? new OperationalOccupancyPeriod(CarbonImmutable::instance($reservation->starts_at), CarbonImmutable::instance($reservation->ends_at))
                : $this->occupancy->calculate($resource, $booking->starts_at, $booking->ends_at);
            if ($period === null || ! $block->starts_at->lt($period->endsAt) || ! $block->ends_at->gt($period->startsAt)) {
                continue;
            }
            $impact = new AvailabilityBlockBookingImpact;
            $impact->forceFill([
                'availability_block_id' => $block->id,
                'booking_id' => $booking->id,
                'detected_at' => $evaluatedAt,
                'status' => ClosureImpactStatus::Unresolved,
            ])->save();
            activity('closure')->performedOn($impact)->causedBy($actor)->event('closure.booking_affected')
                ->withProperties(['availability_block_id' => $block->id, 'booking_id' => $booking->id, 'centre_id' => $centreId])
                ->log('Booking requires closure review');
            $created++;
        }

        return $created;
    }
}
