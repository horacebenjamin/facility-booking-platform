<?php

namespace App\Enums;

enum ClosureImpactStatus: string
{
    case Unresolved = 'unresolved';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Unresolved => 'Unresolved',
            self::Resolved => 'Resolved',
        };
    }
}
