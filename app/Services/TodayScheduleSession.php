<?php

namespace App\Services;

use App\Enums\AttendanceState;
use Carbon\CarbonImmutable;

final readonly class TodayScheduleSession
{
    /**
     * @param  list<array{name: string, quantity: int}>  $equipment
     * @param  list<AttendanceState>  $availableTransitions
     */
    public function __construct(
        public int $bookingId,
        public string $reference,
        public string $customerName,
        public string $facilityName,
        public string $resourceName,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public CarbonImmutable $operationalStartsAt,
        public CarbonImmutable $operationalEndsAt,
        public ?string $seriesIdentifier,
        public ?int $occurrenceIndex,
        public ?int $occurrenceCount,
        public array $equipment,
        public AttendanceState $attendanceState,
        public ?CarbonImmutable $arrivedAt,
        public ?CarbonImmutable $noShowRecordedAt,
        public ?CarbonImmutable $completedAt,
        public array $availableTransitions,
    ) {}

    public function phase(CarbonImmutable $at): string
    {
        return match (true) {
            $at->lt($this->operationalStartsAt) => 'Upcoming',
            $at->lt($this->startsAt) => 'Setup',
            $at->lt($this->endsAt) => 'Session',
            $at->lt($this->operationalEndsAt) => 'Cleanup',
            default => 'Ended',
        };
    }
}
