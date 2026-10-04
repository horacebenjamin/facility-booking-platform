<?php

namespace App\Listeners;

use App\Events\LifecycleNotificationRequested;
use App\Services\LifecycleNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class QueueCustomerLifecycleNotification
{
    public function __construct(private LifecycleNotificationService $notifications) {}

    public function handle(LifecycleNotificationRequested $event): void
    {
        try {
            $this->notifications->queueActivity($event->activityId);
        } catch (Throwable $exception) {
            try {
                Log::warning('Customer notification delivery requires retry.', ['activity_id' => $event->activityId, 'exception_type' => $exception::class]);
            } catch (Throwable) {
            }
        }
    }
}
