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
        $resources = $this->scopes->resourcesFor($block)->get()->keyBy('id');
        if ($resources->isEmpty()) {
            return 0;
        }
        $centreId = $block->owningCentre()->id;
        $earliestEnd = CarbonImmutable::instance($block->starts_at)->subMinutes((int) $resources->max('cleanup_minutes'));
        $latestStart = CarbonImmutable::instance($block->ends_at)->addMinutes((int) $resources->max('setup_minutes'));
        $unitIds = $block->blockScope() === AvailabilityBlockScope::Resource
            ? $block->resource->allocationUnits()->pluck('allocation_units.id')->all() : [];
        $bookings = Booking::query()->where('centre_id', $centreId)
            ->where(function (Builder $query) use ($block, $resources, $unitIds): void {
                $query->whereIn('resource_id', $resources->modelKeys());
                if ($block->blockScope() === AvailabilityBlockScope::Resource) {
                    $query->orWhereHas('allocationOccupancy.allocationUnits', fn (Builder $query) => $query->whereIn('allocation_units.id', $unitIds));
                }
            })
            ->where(function (Builder $query) use ($block, $latestStart, $earliestEnd): void {
                $query->whereHas('allocationOccupancy', fn (Builder $query) => $query->where('starts_at', '<', $block->ends_at)->where('ends_at', '>', $block->starts_at))
                    ->orWhere(fn (Builder $query) => $query->doesntHave('allocationOccupancy')->where('starts_at', '<', $latestStart)->where('ends_at', '>', $earliestEnd));
            })
            ->where(function (Builder $query) use ($evaluatedAt): void {
                $query->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Approved])
                    ->orWhere(fn (Builder $query) => $query->where('status', BookingStatus::Requested)
                        ->whereHas('allocationOccupancy', fn (Builder $query) => $query->where('expires_at', '>', $evaluatedAt)));
            })->with('allocationOccupancy.allocationUnits')->orderBy('id')->get();
        $existing = AvailabilityBlockBookingImpact::query()->where('availability_block_id', $block->id)->pluck('booking_id')->all();
        $created = 0;
        foreach ($bookings as $booking) {
            $resource = $resources->get($booking->resource_id);
            if (in_array($booking->id, $existing, true)) {
                continue;
            }
            $reservation = $booking->allocationOccupancy;
            if ($reservation !== null && $block->blockScope() === AvailabilityBlockScope::Resource
                && $booking->resource_id !== $block->resource_id
                && $reservation->allocationUnits->pluck('id')->intersect($unitIds)->isEmpty()) {
                continue;
            }
            $period = $reservation !== null
                ? new OperationalOccupancyPeriod(CarbonImmutable::instance($reservation->starts_at), CarbonImmutable::instance($reservation->ends_at))
                : ($resource === null ? null : $this->occupancy->calculate($resource, $booking->starts_at, $booking->ends_at));
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
