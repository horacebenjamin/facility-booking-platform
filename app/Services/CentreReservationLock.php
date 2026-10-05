<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Centre;
use App\Models\Resource;
use Illuminate\Support\Facades\DB;
use LogicException;

class CentreReservationLock
{
    public function lock(int $centreId): Centre
    {
        $this->assertTransaction();

        return Centre::query()->whereKey($centreId)->lockForUpdate()->firstOrFail();
    }

    public function centreIdForResource(int $resourceId): int
    {
        return Resource::query()->with('facility')->findOrFail($resourceId)->facility->centre_id;
    }

    public function centreIdForBooking(int $bookingId): int
    {
        return Booking::query()->findOrFail($bookingId)->centre_id;
    }

    private function assertTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Centre reservation locks require an active transaction.');
        }
    }
}
