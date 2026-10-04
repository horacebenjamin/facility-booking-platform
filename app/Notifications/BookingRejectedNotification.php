<?php

namespace App\Notifications;

class BookingRejectedNotification extends CustomerLifecycleNotification
{
    public const Type = 'booking.rejected';
}
