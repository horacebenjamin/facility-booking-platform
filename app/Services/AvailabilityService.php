<?php

namespace App\Services;

use App\Enums\AvailabilityReason;
use App\Models\Facility;
use App\Models\Resource;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class AvailabilityService
{
    public function __construct(
        private BookableHoursEvaluator $bookableHoursEvaluator,
        private AvailabilityBlockEvaluator $availabilityBlockEvaluator,
        private OperationalOccupancyCalculator $operationalOccupancyCalculator,
        private AllocationConflictEvaluator $allocationConflictEvaluator,
        private EquipmentAvailabilityEvaluator $equipmentAvailabilityEvaluator,
    ) {}

    /**
     * Authoritative callers must hold the centre reservation lock and request current reservation reads.
     *
     * @param  list<EquipmentRequirement>  $equipmentRequirements
     */
    public function check(
        Resource $resource,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        array $equipmentRequirements = [],
        ?CarbonInterface $evaluatedAt = null,
        ?int $excludedBookingId = null,
        bool $lockReservations = false,
    ): AvailabilityResult {
        if ($lockReservations && DB::transactionLevel() === 0) {
            throw new LogicException('Authoritative availability requires a reservation transaction.');
        }
        $startsAt = CarbonImmutable::instance($startsAt)->setTimezone(config('app.timezone'));
        $endsAt = CarbonImmutable::instance($endsAt)->setTimezone(config('app.timezone'));

        if (! $startsAt->lt($endsAt)) {
            return new AvailabilityResult(AvailabilityReason::InvalidPeriod);
        }

        $evaluatedAt = CarbonImmutable::instance($evaluatedAt ?? now())
            ->setTimezone(config('app.timezone'));
        $facility = $resource->facility;
        $reasons = $this->inactiveHierarchyReasons($resource, $facility);

        if ($reasons !== []) {
            return new AvailabilityResult(...$reasons);
        }

        if (! $this->bookableHoursEvaluator->facilityContainsRequest($facility, $startsAt, $endsAt)) {
            $reasons[] = AvailabilityReason::OutsideBookableHours;
        }

        if (! $this->bookableHoursEvaluator->resourceContainsRequest($resource, $startsAt, $endsAt)) {
            $reasons[] = AvailabilityReason::OutsideBookableHours;
        }

        $operationalPeriod = $this->operationalOccupancyCalculator->calculate($resource, $startsAt, $endsAt);

        if ($operationalPeriod !== null && $this->availabilityBlockEvaluator->isBlocked(
            $resource,
            $operationalPeriod->startsAt,
            $operationalPeriod->endsAt,
            $lockReservations,
        )) {
            $reasons[] = AvailabilityReason::Blockout;
        }

        if ($operationalPeriod !== null && $this->allocationConflictEvaluator->hasConflict(
            $resource,
            $operationalPeriod->startsAt,
            $operationalPeriod->endsAt,
            $evaluatedAt,
            $excludedBookingId,
            $lockReservations,
        )) {
            $reasons[] = AvailabilityReason::ResourceConflict;
        }

        foreach ($equipmentRequirements as $equipmentRequirement) {
            if (! $this->equipmentRequirementIsCompatible($equipmentRequirement, $facility)
                || ! $this->equipmentAvailabilityEvaluator->isAvailable(
                    $equipmentRequirement->equipment,
                    $equipmentRequirement->quantity,
                    $startsAt,
                    $endsAt,
                    $evaluatedAt,
                    $excludedBookingId,
                    $lockReservations,
                )) {
                $reasons[] = AvailabilityReason::EquipmentUnavailable;
            }
        }

        return new AvailabilityResult(...$reasons);
    }

    /**
     * @return list<AvailabilityReason>
     */
    private function inactiveHierarchyReasons(Resource $resource, Facility $facility): array
    {
        $reasons = [];

        if (! $facility->centre->is_active) {
            $reasons[] = AvailabilityReason::InactiveCentre;
        }

        if (! $facility->is_active) {
            $reasons[] = AvailabilityReason::InactiveFacility;
        }

        if (! $resource->is_active) {
            $reasons[] = AvailabilityReason::InactiveResource;
        }

        return $reasons;
    }

    private function equipmentRequirementIsCompatible(EquipmentRequirement $equipmentRequirement, Facility $facility): bool
    {
        $equipment = $equipmentRequirement->equipment;

        return $equipment->centre_id === $facility->centre_id
            && ($equipment->facility_id === null || $equipment->facility_id === $facility->id);
    }
}
