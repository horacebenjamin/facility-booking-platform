<?php

namespace App\Enums;

enum RecurringBookingConflictReason: string
{
    case Availability = 'availability';
    case Pricing = 'pricing';
    case ResourceAllocationUnavailable = 'resource_allocation_unavailable';
    case SeriesOverlap = 'series_overlap';
}
