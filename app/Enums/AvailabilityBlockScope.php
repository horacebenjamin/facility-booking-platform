<?php

namespace App\Enums;

enum AvailabilityBlockScope: string
{
    case Centre = 'centre';
    case Facility = 'facility';
    case Resource = 'resource';

    public function label(): string
    {
        return match ($this) {
            self::Centre => 'Centre',
            self::Facility => 'Facility',
            self::Resource => 'Resource',
        };
    }
}
