<?php

namespace App\Notifications;

class PaymentReceivedNotification extends CustomerLifecycleNotification
{
    public const Type = 'payment.received';
}
