<?php

namespace App\Actions;

use App\Models\AvailabilityBlock;
use App\Models\User;
use App\Services\CentreReservationLock;
use App\Services\ClosureAuthorization;
use App\Services\ClosureImpactService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DetectClosureAffectedBookings
{
    public function __construct(
        private CentreReservationLock $reservationLock,
        private ClosureAuthorization $authorization,
        private ClosureImpactService $impacts,
    ) {}

    public function handle(User $actor, AvailabilityBlock $block): int
    {
        $block = AvailabilityBlock::query()->findOrFail($block->id);
        $actor = $this->authorization->authorizeBlock($actor, $block);
        $centreId = $block->owningCentre()->id;

        return DB::transaction(function () use ($actor, $block, $centreId): int {
            $this->reservationLock->lock($centreId);
            $block = AvailabilityBlock::query()->lockForUpdate()->findOrFail($block->id);
            $actor = $this->authorization->authorizeBlock($actor, $block);

            return $this->impacts->detect($actor, $block, CarbonImmutable::now(config('app.timezone')));
        });
    }
}
