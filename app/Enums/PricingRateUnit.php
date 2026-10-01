<?php

namespace App\Enums;

enum PricingRateUnit: string
{
    case Hourly = 'hour';

    public function label(): string
    {
        return 'Per hour';
    }
}
