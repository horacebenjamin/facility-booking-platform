<?php

namespace App\Enums;

enum AvailabilityReason: string
{
    case InvalidPeriod = 'invalid_period';
    case InactiveCentre = 'inactive_centre';
    case InactiveFacility = 'inactive_facility';
    case InactiveResource = 'inactive_resource';
    case OutsideBookableHours = 'outside_bookable_hours';
    case ResourceConflict = 'resource_conflict';
    case Blockout = 'blockout';
    case EquipmentUnavailable = 'equipment_unavailable';
}
