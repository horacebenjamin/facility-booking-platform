<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use LogicException;

class RecurrenceGenerator
{
    /**
     * @return list<OccurrencePeriod>
     */
    public function generate(
        CarbonInterface $firstStartsAt,
        CarbonInterface $firstEndsAt,
        RecurrencePattern $pattern,
    ): array {
        $localStartsAt = CarbonImmutable::instance($firstStartsAt)->setTimezone($pattern->timezone);
        $localEndsAt = CarbonImmutable::instance($firstEndsAt)->setTimezone($pattern->timezone);

        if (! $localStartsAt->lt($localEndsAt)) {
            throw new InvalidArgumentException('The first occurrence must end after it starts.');
        }

        $durationSeconds = $localEndsAt->getTimestamp() - $localStartsAt->getTimestamp();
        $applicationTimezone = config('app.timezone');
        $periods = [];
        $identities = [];

        for ($offset = 0; $offset < $pattern->occurrenceCount; $offset++) {
            $startsAt = $localStartsAt->addWeeks($offset * $pattern->intervalWeeks);
            $endsAt = $startsAt->addSeconds($durationSeconds);
            $identity = $startsAt->getTimestamp().':'.$endsAt->getTimestamp();

            if (isset($identities[$identity])) {
                throw new LogicException('The recurrence pattern generated a duplicate occurrence.');
            }

            $identities[$identity] = true;
            $periods[] = new OccurrencePeriod(
                index: $offset + 1,
                startsAt: $startsAt->setTimezone($applicationTimezone),
                endsAt: $endsAt->setTimezone($applicationTimezone),
            );
        }

        return $periods;
    }
}
