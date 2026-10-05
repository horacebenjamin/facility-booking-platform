<?php

namespace App\Actions;

use App\Enums\ClosureFinancialRequirement;
use App\Enums\ClosureImpactStatus;
use App\Enums\ClosureOperationalRequirement;
use App\Models\AvailabilityBlock;
use App\Models\AvailabilityBlockBookingImpact;
use App\Models\User;
use App\Services\CentreReservationLock;
use App\Services\ClosureAuthorization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ResolveClosureImpact
{
    public function __construct(private CentreReservationLock $reservationLock, private ClosureAuthorization $authorization) {}

    public function handle(User $actor, AvailabilityBlockBookingImpact $impact, string $notes): AvailabilityBlockBookingImpact
    {
        $impact = AvailabilityBlockBookingImpact::query()->with('availabilityBlock')->whereKey($impact->id)->firstOrFail();
        $actor = $this->authorization->authorizeImpact($actor, $impact);
        $centreId = $impact->availabilityBlock->owningCentre()->id;
        $notes = trim($notes);
        Validator::make(['resolution_notes' => $notes], ['resolution_notes' => ['required', 'string', 'max:2000']])->validate();

        return DB::transaction(function () use ($actor, $impact, $centreId, $notes): AvailabilityBlockBookingImpact {
            $this->reservationLock->lock($centreId);
            $block = AvailabilityBlock::query()->whereKey($impact->availability_block_id)->lockForUpdate()->firstOrFail();
            $impact = AvailabilityBlockBookingImpact::query()->whereKey($impact->id)->lockForUpdate()->firstOrFail();
            $actor = $this->authorization->authorizeImpact($actor, $impact);
            if ($block->owningCentre()->id !== $centreId || $impact->availability_block_id !== $block->id || $impact->status !== ClosureImpactStatus::Unresolved) {
                throw ValidationException::withMessages(['resolution_notes' => 'This impact is already resolved. Refresh the closure.']);
            }
            if ($impact->operational_requirement !== ClosureOperationalRequirement::NoActionRequired) {
                throw ValidationException::withMessages(['resolution_notes' => 'An operational review or action is still required. Rescheduling and cancellation are not executed by this workflow.']);
            }
            if ($impact->financial_requirement === ClosureFinancialRequirement::ReviewRequired) {
                throw ValidationException::withMessages(['resolution_notes' => 'Record the required financial follow-up before resolving this operational impact.']);
            }
            if ($impact->communication_required && $impact->communicated_at === null) {
                throw ValidationException::withMessages(['resolution_notes' => 'Record customer communication before resolving this impact.']);
            }
            $recordedAt = CarbonImmutable::now(config('app.timezone'));
            $impact->forceFill(['status' => ClosureImpactStatus::Resolved, 'resolved_at' => $recordedAt, 'resolved_by' => $actor->id, 'resolution_notes' => $notes])->save();
            activity('closure')->performedOn($impact)->causedBy($actor)->event('closure.impact_resolved')
                ->withProperties(['centre_id' => $centreId, 'before' => ClosureImpactStatus::Unresolved->value, 'after' => ClosureImpactStatus::Resolved->value, 'financial_requirement' => $impact->financial_requirement->value, 'recorded_at' => $recordedAt->toIso8601String()])
                ->log('Closure operational impact resolved; financial follow-up remains separate');

            return $impact;
        });
    }
}
