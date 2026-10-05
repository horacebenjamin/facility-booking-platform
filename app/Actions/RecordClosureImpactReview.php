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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecordClosureImpactReview
{
    public function __construct(private CentreReservationLock $reservationLock, private ClosureAuthorization $authorization) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, AvailabilityBlockBookingImpact $impact, array $data): AvailabilityBlockBookingImpact
    {
        $impact = AvailabilityBlockBookingImpact::query()->with('availabilityBlock')->whereKey($impact->id)->firstOrFail();
        $actor = $this->authorization->authorizeImpact($actor, $impact);
        $centreId = $impact->availabilityBlock->owningCentre()->id;
        if (isset($data['review_notes']) && is_string($data['review_notes'])) {
            $data['review_notes'] = trim($data['review_notes']);
        }
        foreach (['operational_requirement', 'financial_requirement'] as $key) {
            if (($data[$key] ?? null) instanceof \BackedEnum) {
                $data[$key] = $data[$key]->value;
            }
        }
        $data = Validator::make($data, [
            'operational_requirement' => ['required', Rule::enum(ClosureOperationalRequirement::class)],
            'financial_requirement' => ['required', Rule::enum(ClosureFinancialRequirement::class)],
            'communication_required' => ['required', 'boolean'],
            'review_notes' => ['required', 'string', 'max:2000'],
        ])->validate();

        return DB::transaction(function () use ($actor, $impact, $centreId, $data): AvailabilityBlockBookingImpact {
            $this->reservationLock->lock($centreId);
            $block = AvailabilityBlock::query()->whereKey($impact->availability_block_id)->lockForUpdate()->firstOrFail();
            $impact = AvailabilityBlockBookingImpact::query()->whereKey($impact->id)->lockForUpdate()->firstOrFail();
            $actor = $this->authorization->authorizeImpact($actor, $impact);
            if ($block->owningCentre()->id !== $centreId || $impact->availability_block_id !== $block->id || $impact->status !== ClosureImpactStatus::Unresolved) {
                throw ValidationException::withMessages(['review_notes' => 'Only unresolved impacts can be reviewed. Refresh the closure.']);
            }
            $operational = ClosureOperationalRequirement::from($data['operational_requirement']);
            $financial = ClosureFinancialRequirement::from($data['financial_requirement']);
            $communication = (bool) $data['communication_required'];
            if ($impact->operational_requirement === $operational && $impact->financial_requirement === $financial
                && $impact->communication_required === $communication && $impact->review_notes === $data['review_notes']) {
                return $impact;
            }
            $before = ['operational_requirement' => $impact->operational_requirement->value, 'financial_requirement' => $impact->financial_requirement->value, 'communication_required' => $impact->communication_required];
            $impact->forceFill(['operational_requirement' => $operational, 'financial_requirement' => $financial, 'communication_required' => $communication, 'review_notes' => $data['review_notes']])->save();
            activity('closure')->performedOn($impact)->causedBy($actor)->event('closure.review_recorded')
                ->withProperties(['centre_id' => $centreId, 'before' => $before, 'after' => ['operational_requirement' => $operational->value, 'financial_requirement' => $financial->value, 'communication_required' => $communication]])
                ->log('Closure impact requirements reviewed');

            return $impact;
        });
    }
}
