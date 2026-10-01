<?php

namespace App\Enums;

enum PricingFailureReason: string
{
    case InvalidRequest = 'invalid_request';
    case MissingResourceRate = 'missing_resource_rate';
    case AmbiguousResourceRate = 'ambiguous_resource_rate';
    case MissingEquipmentRate = 'missing_equipment_rate';
    case AmbiguousEquipmentRate = 'ambiguous_equipment_rate';
    case CurrencyMismatch = 'currency_mismatch';
    case UnauthorizedOverride = 'unauthorized_override';
    case InvalidOverride = 'invalid_override';
}
