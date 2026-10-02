<?php

namespace Tests\Unit\Services;

use App\Enums\RecurrenceFrequency;
use App\Services\RecurrenceGenerator;
use App\Services\RecurrencePattern;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecurrenceGeneratorTest extends TestCase
{
    public function test_weekly_occurrences_are_finite_ordered_unique_and_preserve_duration(): void
    {
        $periods = (new RecurrenceGenerator)->generate(
            CarbonImmutable::parse('2026-10-05 18:00:00', 'Europe/London'),
            CarbonImmutable::parse('2026-10-05 19:30:00', 'Europe/London'),
            new RecurrencePattern(RecurrenceFrequency::Weekly, 1, 4, 'Europe/London'),
        );

        $this->assertCount(4, $periods);
        $this->assertSame([1, 2, 3, 4], array_column($periods, 'index'));
        $this->assertSame([
            '2026-10-05 17:00:00',
            '2026-10-12 17:00:00',
            '2026-10-19 17:00:00',
            '2026-10-26 18:00:00',
        ], array_map(static fn ($period): string => $period->startsAt->toDateTimeString(), $periods));
        $this->assertSame([5400, 5400, 5400, 5400], array_map(
            static fn ($period): int => $period->endsAt->getTimestamp() - $period->startsAt->getTimestamp(),
            $periods,
        ));
        $this->assertSame(4, collect($periods)->unique(
            static fn ($period): string => $period->startsAt->getTimestamp().':'.$period->endsAt->getTimestamp(),
        )->count());
    }

    public function test_fixed_week_interval_generates_the_requested_number_of_occurrences(): void
    {
        $periods = (new RecurrenceGenerator)->generate(
            CarbonImmutable::parse('2026-01-05 10:00:00', 'Europe/London'),
            CarbonImmutable::parse('2026-01-05 11:00:00', 'Europe/London'),
            new RecurrencePattern(RecurrenceFrequency::Weekly, 2, 3, 'Europe/London'),
        );

        $this->assertSame([
            '2026-01-05 10:00:00',
            '2026-01-19 10:00:00',
            '2026-02-02 10:00:00',
        ], array_map(static fn ($period): string => $period->startsAt->toDateTimeString(), $periods));
    }

    public function test_weekly_local_start_time_does_not_drift_across_spring_dst(): void
    {
        $periods = (new RecurrenceGenerator)->generate(
            CarbonImmutable::parse('2026-03-22 10:00:00', 'Europe/London'),
            CarbonImmutable::parse('2026-03-22 11:00:00', 'Europe/London'),
            new RecurrencePattern(RecurrenceFrequency::Weekly, 1, 3, 'Europe/London'),
        );

        $this->assertSame([
            '2026-03-22 10:00:00',
            '2026-03-29 10:00:00',
            '2026-04-05 10:00:00',
        ], array_map(
            static fn ($period): string => $period->startsAt->setTimezone('Europe/London')->toDateTimeString(),
            $periods,
        ));
        $this->assertSame([3600, 3600, 3600], array_map(
            static fn ($period): int => $period->endsAt->getTimestamp() - $period->startsAt->getTimestamp(),
            $periods,
        ));
    }

    #[DataProvider('invalidPatterns')]
    public function test_invalid_weekly_patterns_are_rejected(int $intervalWeeks, int $occurrenceCount, string $timezone): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RecurrencePattern(RecurrenceFrequency::Weekly, $intervalWeeks, $occurrenceCount, $timezone);
    }

    /**
     * @return array<string, array{int, int, string}>
     */
    public static function invalidPatterns(): array
    {
        return [
            'zero interval' => [0, 2, 'Europe/London'],
            'interval exceeds persistence range' => [256, 2, 'Europe/London'],
            'single occurrence' => [1, 1, 'Europe/London'],
            'count exceeds transaction limit' => [1, 105, 'Europe/London'],
            'unknown timezone' => [1, 2, 'Not/A_Timezone'],
        ];
    }

    public function test_non_increasing_first_period_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RecurrenceGenerator)->generate(
            CarbonImmutable::parse('2026-10-05 18:00:00', 'Europe/London'),
            CarbonImmutable::parse('2026-10-05 18:00:00', 'Europe/London'),
            new RecurrencePattern(RecurrenceFrequency::Weekly, 1, 2, 'Europe/London'),
        );
    }
}
