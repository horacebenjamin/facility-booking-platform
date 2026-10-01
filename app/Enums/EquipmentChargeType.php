<?php

namespace App\Enums;

enum EquipmentChargeType: string
{
    case Included = 'included';
    case SeparatelyChargeable = 'separately_chargeable';

    public function label(): string
    {
        return match ($this) {
            self::Included => 'Included',
            self::SeparatelyChargeable => 'Separately chargeable',
        };
    }
}
