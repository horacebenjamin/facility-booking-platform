<?php

namespace App\Enums;

enum BillingMethod: string
{
    case Card = 'card';
    case Invoice = 'invoice';
}
