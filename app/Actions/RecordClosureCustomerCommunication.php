<?php

namespace App\Actions;

use App\Enums\ClosureImpactStatus;
use App\Models\AvailabilityBlock;
use App\Models\AvailabilityBlockBookingImpact;
use App\Models\User;
use App\Services\CentreReservationLock;
use App\Services\ClosureAuthorization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RecordClosureCustomerCommunication
{
    public function __construct(private CentreReservationLock $reservationLock, private ClosureAuthorization $authorization) {}

    public function handle(User $actor, AvailabilityBlockBookingImpact $impact, string $notes): AvailabilityBlockBookingImpact
    {
        $impact = AvailabilityBlockBookingImpact::query()->with('availabilityBlock')->whereKey($impact->id)->firstOrFail();
        $actor = $this->authorization->authorizeImpact($actor, $impact);
        $centreId = $impact->availabilityBlock->owningCentre()->id;
        $notes = trim($notes);
        Validator::make(['communication_notes' => $notes], ['communication_notes' => ['required', 'string', 'max:2000']])->validate();

        return DB::transaction(function () use ($actor, $impact, $centreId, $notes): AvailabilityBlockBookingImpact {
            $this->reservationLock->lock($centreId);
            $block = AvailabilityBlock::query()->whereKey($impact->availability_block_id)->lockForUpdate()->firstOrFail();
            $impact = AvailabilityBlockBookingImpact::query()->whereKey($impact->id)->lockForUpdate()->firstOrFail();
            $actor = $this->authorization->authorizeImpact($actor, $impact);
            if ($block->owningCentre()->id !== $centreId || $impact->availability_block_id !== $block->id || $impact->status !== ClosureImpactStatus::Unresolved || $impact->communicated_at !== null) {
                throw ValidationException::withMessages(['communication_notes' => 'Communication can only be recorded once for an unresolved impact. Refresh the closure.']);
            }
            $recordedAt = CarbonImmutable::now(config('app.timezone'));
            $impact->forceFill(['communicated_at' => $recordedAt, 'communicated_by' => $actor->id, 'communication_notes' => $notes])->save();
            activity('closure')->performedOn($impact)->causedBy($actor)->event('closure.customer_communication_recorded')
                ->withProperties(['centre_id' => $centreId, 'recorded_at' => $recordedAt->toIso8601String()])->log('Customer communication recorded by manager');

            return $impact;
        });
    }
}
