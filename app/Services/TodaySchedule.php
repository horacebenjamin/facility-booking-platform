<?php

namespace App\Services;

use Carbon\CarbonImmutable;

final readonly class TodaySchedule
{
    /**
     * @param  list<TodayScheduleSession>  $sessions
     * @param  list<TodayScheduleSession>  $now
     * @param  list<TodayScheduleSession>  $next
     */
    public function __construct(
        public string $date,
        public CarbonImmutable $refreshedAt,
        public array $sessions,
        public array $now,
        public array $next,
    ) {}
}
