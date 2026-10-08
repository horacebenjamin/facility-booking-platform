<?php

return [
    'provisional_hold_hours' => (int) env('BOOKING_PROVISIONAL_HOLD_HOURS', 48),
    'payment_deadline_hours' => (int) env('BOOKING_PAYMENT_DEADLINE_HOURS', 24),
    'recurrence_timezone' => 'Europe/London',
    // Local wall-clock timezone in which staff enter and read booking times. Instants are stored in UTC.
    'local_timezone' => 'Europe/London',
    'cancellation' => [
        'enabled' => (bool) env('BOOKING_CUSTOMER_CANCELLATION_ENABLED', true),
        'customer_allowed_statuses' => ['requested', 'approved', 'confirmed'],
        'minimum_notice_minutes' => (int) env('BOOKING_CANCELLATION_MINIMUM_NOTICE_MINUTES', 0),
    ],
    'amendment' => [
        'customer_allowed_statuses' => ['requested'],
    ],
];
