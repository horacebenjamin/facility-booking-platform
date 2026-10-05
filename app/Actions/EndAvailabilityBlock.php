<?php

namespace App\Actions;

use App\Models\AvailabilityBlock;
use App\Models\User;
use App\Services\CentreReservationLock;
use App\Services\ClosureAuthorization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EndAvailabilityBlock
{
    public function __construct(private CentreReservationLock $reservationLock, private ClosureAuthorization $authorization) {}

    public function handle(User $actor, AvailabilityBlock $block): AvailabilityBlock
    {
        $block = AvailabilityBlock::query()->whereKey($block->id)->firstOrFail();
        $actor = $this->authorization->authorizeBlock($actor, $block, 'end');
        $centreId = $block->owningCentre()->id;

        return DB::transaction(function () use ($actor, $block, $centreId): AvailabilityBlock {
            $this->reservationLock->lock($centreId);
            $block = AvailabilityBlock::query()->whereKey($block->id)->lockForUpdate()->firstOrFail();
            $actor = $this->authorization->authorizeBlock($actor, $block, 'end');
            if ($block->owningCentre()->id !== $centreId || $block->ended_at !== null) {
                throw ValidationException::withMessages(['ended_at' => 'This closure has already ended. Refresh the closure.']);
            }
            $recordedAt = CarbonImmutable::now(config('app.timezone'));
            $block->forceFill(['ended_at' => $recordedAt, 'ended_by' => $actor->id])->save();
            activity('closure')->performedOn($block)->causedBy($actor)->event('closure.ended')
                ->withProperties(['centre_id' => $centreId, 'ended_at' => $recordedAt->toIso8601String(), 'effective_ends_at' => $block->effectiveEndsAt()->toIso8601String()])
                ->log('Availability block ended without changing its original period');

            return $block;
        });
    }
}
