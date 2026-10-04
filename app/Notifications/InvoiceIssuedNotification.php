<?php

namespace App\Notifications;

class InvoiceIssuedNotification extends CustomerLifecycleNotification
{
    public const Type = 'invoice.issued';
}
