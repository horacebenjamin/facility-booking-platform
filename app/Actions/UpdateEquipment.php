<?php

namespace App\Actions;

use App\Models\Centre;
use App\Models\Equipment;
use App\Models\Facility;
use App\Models\User;
use App\Services\CentreReservationLock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateEquipment
{
    public function __construct(private CentreReservationLock $reservationLock) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, Equipment $equipment, array $data): Equipment
    {
        $data = Validator::make($data, [
            'centre_id' => ['sometimes', 'required', 'integer', 'min:1'],
            'facility_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'quantity' => ['sometimes', 'required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ])->validate();
        $sourceCentreId = Equipment::query()->findOrFail($equipment->id)->centre_id;
        $targetCentreId = (int) ($data['centre_id'] ?? $sourceCentreId);

        return DB::transaction(function () use ($actor, $equipment, $data, $sourceCentreId, $targetCentreId): Equipment {
            $centreIds = array_unique([$sourceCentreId, $targetCentreId]);
            sort($centreIds, SORT_NUMERIC);
            foreach ($centreIds as $centreId) {
                $this->reservationLock->lock($centreId);
            }
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($equipment->id);
            if ($equipment->centre_id !== $sourceCentreId) {
                throw ValidationException::withMessages(['centre_id' => 'The equipment location changed. Reload and try again.']);
            }
            $actor = User::query()->findOrFail($actor->id);
            foreach ($centreIds as $centreId) {
                if (! $actor->hasRole('manager') || ! $actor->can('facilities.manage')
                    || ! $actor->isAssignedToCentre(Centre::query()->findOrFail($centreId))) {
                    throw new AuthorizationException;
                }
            }
            $facilityId = array_key_exists('facility_id', $data) ? $data['facility_id'] : $equipment->facility_id;
            $facilityId = $facilityId === null ? null : (int) $facilityId;
            if ($facilityId !== null && Facility::query()->whereKey($facilityId)->where('centre_id', $targetCentreId)->lockForUpdate()->first() === null) {
                throw ValidationException::withMessages(['facility_id' => 'Select a facility belonging to the equipment centre.']);
            }
            $now = now();
            $allocations = $equipment->allocations()->where('ends_at', '>', $now)
                ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', $now))
                ->orderBy('id')->lockForUpdate()->get();
            if ($allocations->isNotEmpty() && ($targetCentreId !== $equipment->centre_id || $facilityId !== $equipment->facility_id)) {
                throw ValidationException::withMessages(['facility_id' => 'Equipment with active reservations cannot be moved.']);
            }
            if (isset($data['quantity'])) {
                $changes = [];
                foreach ($allocations as $allocation) {
                    $start = max($now->getTimestamp(), $allocation->starts_at->getTimestamp());
                    $end = $allocation->ends_at->getTimestamp();
                    $changes[$start] = ($changes[$start] ?? 0) + $allocation->quantity;
                    $changes[$end] = ($changes[$end] ?? 0) - $allocation->quantity;
                }
                ksort($changes, SORT_NUMERIC);
                $allocated = 0;
                foreach ($changes as $change) {
                    $allocated += $change;
                    if ($allocated > (int) $data['quantity']) {
                        throw ValidationException::withMessages(['quantity' => 'Quantity cannot be reduced below active simultaneous reservations.']);
                    }
                }
            }
            $equipment->update([...$data, 'centre_id' => $targetCentreId, 'facility_id' => $facilityId]);

            return $equipment;
        });
    }
}
