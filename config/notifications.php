<?php

return [
    'queue_connection' => env('NOTIFICATION_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'redis')),
    'queue' => 'notifications',
];
