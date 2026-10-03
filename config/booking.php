<?php

return [
    'provisional_hold_hours' => (int) env('BOOKING_PROVISIONAL_HOLD_HOURS', 48),
    'payment_deadline_hours' => (int) env('BOOKING_PAYMENT_DEADLINE_HOURS', 24),
    'recurrence_timezone' => 'Europe/London',
];
