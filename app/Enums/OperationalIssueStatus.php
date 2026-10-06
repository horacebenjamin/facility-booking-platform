<?php

namespace App\Enums;

enum OperationalIssueStatus: string
{
    case Open = 'open';
    case Reviewed = 'reviewed';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Reviewed => 'Reviewed',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(self::cases(), function (array $options, self $status): array {
            $options[$status->value] = $status->label();

            return $options;
        }, []);
    }

    /** @return list<self> */
    public function nextStates(): array
    {
        return match ($this) {
            self::Open => [self::Reviewed],
            self::Reviewed => [self::Resolved],
            self::Resolved => [self::Closed],
            self::Closed => [],
        };
    }
}
