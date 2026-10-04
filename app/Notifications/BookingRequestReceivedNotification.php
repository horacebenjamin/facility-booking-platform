<?php

namespace App\Notifications;

class BookingRequestReceivedNotification extends CustomerLifecycleNotification
{
    public const Type = 'booking.requested';
}
