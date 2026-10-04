<?php

namespace App\Notifications;

class BookingApprovedPaymentRequiredNotification extends CustomerLifecycleNotification
{
    public const Type = 'booking.approved_payment_required';
}
