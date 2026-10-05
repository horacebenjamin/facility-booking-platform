<?php

namespace App\Enums;

enum AvailabilityBlockType: string
{
    case SchoolUse = 'school_use';
    case Exam = 'exam';
    case Maintenance = 'maintenance';
    case HealthAndSafety = 'health_and_safety';
    case Weather = 'weather';
    case InternalUse = 'internal_use';
    case ManagerBlockout = 'manager_blockout';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SchoolUse => 'School use',
            self::Exam => 'Exam',
            self::Maintenance => 'Maintenance',
            self::HealthAndSafety => 'Health and safety',
            self::Weather => 'Weather',
            self::InternalUse => 'Internal/private use',
            self::ManagerBlockout => 'Manager blockout',
            self::Other => 'Other',
        };
    }
}
