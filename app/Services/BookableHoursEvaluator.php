<?php

namespace App\Services;

use App\Enums\DayOfWeek;
use App\Models\Facility;
use App\Models\FacilityBookableHour;
use App\Models\Resource;
use App\Models\ResourceBookableHour;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class BookableHoursEvaluator
{
    public function facilityContainsRequest(
        Facility $facility,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
    ): bool {
        $requestedPeriod = $this->normaliseRequestedPeriod($startsAt, $endsAt);

        if ($requestedPeriod === null) {
            return false;
        }

        /** @var FacilityBookableHour|null $bookableHour */
        $bookableHour = $facility->bookableHours()
            ->where('day_of_week', $requestedPeriod['dayOfWeek']->value)
            ->first();

        return $this->configuredHoursContainRequest(
            $bookableHour,
            $requestedPeriod['startsAt'],
            $requestedPeriod['endsAt'],
        );
    }

    public function resourceContainsRequest(
        Resource $resource,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
    ): bool {
        $requestedPeriod = $this->normaliseRequestedPeriod($startsAt, $endsAt);

        if ($requestedPeriod === null) {
            return false;
        }

        /** @var ResourceBookableHour|null $bookableHour */
        $bookableHour = $resource->bookableHours()
            ->where('day_of_week', $requestedPeriod['dayOfWeek']->value)
            ->first();

        return $this->configuredHoursContainRequest(
            $bookableHour,
            $requestedPeriod['startsAt'],
            $requestedPeriod['endsAt'],
        );
    }

    /**
     * @return array{dayOfWeek: DayOfWeek, startsAt: CarbonImmutable, endsAt: CarbonImmutable}|null
     */
    private function normaliseRequestedPeriod(CarbonInterface $startsAt, CarbonInterface $endsAt): ?array
    {
        $startsAt = BookingDateTime::inLocalTimezone($startsAt);
        $endsAt = BookingDateTime::inLocalTimezone($endsAt);

        if (! $startsAt->lt($endsAt) || ! $startsAt->isSameDay($endsAt)) {
            return null;
        }

        return [
            'dayOfWeek' => DayOfWeek::from($startsAt->isoWeekday()),
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
        ];
    }

    private function configuredHoursContainRequest(
        FacilityBookableHour|ResourceBookableHour|null $bookableHour,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
    ): bool {
        if ($bookableHour === null) {
            return false;
        }

        $opensAt = $startsAt->setTimeFromTimeString($bookableHour->opens_at);
        $closesAt = $startsAt->setTimeFromTimeString($bookableHour->closes_at);

        return $startsAt->greaterThanOrEqualTo($opensAt)
            && $endsAt->lessThanOrEqualTo($closesAt);
    }
}
