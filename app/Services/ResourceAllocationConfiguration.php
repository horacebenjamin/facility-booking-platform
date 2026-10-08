<?php

namespace App\Services;

use App\Models\AllocationUnit;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ResourceAllocationConfiguration
{
    public function __construct(private CentreReservationLock $reservationLock) {}

    /** @param list<int> $unitIds */
    public function replace(Resource $resource, array $unitIds): void
    {
        $this->change($resource, $unitIds, 'replace');
    }

    public function attach(User $actor, Resource $resource, int $unitId): void
    {
        $this->change($resource, [$unitId], 'attach', $actor);
    }

    public function detach(User $actor, Resource $resource, int $unitId): void
    {
        $this->change($resource, [$unitId], 'detach', $actor);
    }

    /** @param list<int> $unitIds */
    private function change(Resource $resource, array $unitIds, string $operation, ?User $actor = null): void
    {
        $centreId = $this->reservationLock->centreIdForResource($resource->id);
        DB::transaction(function () use ($resource, $unitIds, $operation, $actor, $centreId): void {
            $centre = $this->reservationLock->lock($centreId);
            $resource = Resource::query()->lockForUpdate()->findOrFail($resource->id);
            $facility = Facility::query()->where('centre_id', $centreId)->lockForUpdate()->find($resource->facility_id);
            if ($facility === null) {
                throw ValidationException::withMessages(['recordId' => 'The resource location changed. Reload and try again.']);
            }
            if ($actor !== null) {
                $actor = User::query()->findOrFail($actor->id);
                if (! $actor->hasRole('manager') || ! $actor->can('facilities.manage') || ! $actor->isAssignedToCentre($centre)) {
                    throw new AuthorizationException;
                }
            }
            $currentIds = $resource->allocationUnits()->orderBy('allocation_units.id')->lockForUpdate()->pluck('allocation_units.id')->all();
            $targetIds = match ($operation) {
                'attach' => array_merge($currentIds, $unitIds),
                'detach' => array_values(array_diff($currentIds, $unitIds)),
                default => $unitIds,
            };
            $targetIds = array_values(array_unique($targetIds));
            sort($targetIds, SORT_NUMERIC);
            $units = AllocationUnit::query()->where('facility_id', $resource->facility_id)->whereKey($targetIds)->orderBy('id')->lockForUpdate()->get();
            if ($units->count() !== count($targetIds) || $units->contains(fn (AllocationUnit $unit): bool => $unit->facility_id !== $resource->facility_id)) {
                throw new InvalidArgumentException('An allocation unit must belong to the resource facility.');
            }
            if ($targetIds === $currentIds) {
                return;
            }
            $now = now();
            $protected = DB::table('allocation_occupancies as occupancy')
                ->join('bookings', 'bookings.id', '=', 'occupancy.booking_id')
                ->where('bookings.resource_id', $resource->id)->where('occupancy.ends_at', '>', $now)
                ->where(fn (Builder $query) => $query->whereNull('occupancy.expires_at')->orWhere('occupancy.expires_at', '>', $now))
                ->orderBy('occupancy.id')->lockForUpdate()->first(['occupancy.id']);
            if ($protected !== null) {
                throw ValidationException::withMessages(['recordId' => 'Allocation units cannot change while the resource has active booking protection.']);
            }
            $resource->allocationUnits()->syncWithPivotValues($targetIds, ['facility_id' => $resource->facility_id]);
        });
    }
}
