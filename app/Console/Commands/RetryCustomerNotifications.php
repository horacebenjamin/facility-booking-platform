<?php

namespace App\Console\Commands;

use App\Services\LifecycleNotificationPayload;
use App\Services\LifecycleNotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Throwable;

#[Signature('notifications:retry')]
#[Description('Recover undelivered customer lifecycle notifications from committed audit events')]
class RetryCustomerNotifications extends Command
{
    public function handle(LifecycleNotificationService $notifications): int
    {
        $firstId = DB::table('notification_delivery_checkpoints')->where('id', 1)->value('first_activity_id');
        $failures = 0;
        Activity::query()->where('id', '>=', $firstId)->whereIn('event', LifecycleNotificationPayload::SupportedEvents)
            ->chunkById(100, function ($activities) use ($notifications, &$failures): void {
                foreach ($activities as $activity) {
                    try {
                        $notifications->queueActivity($activity->id);
                    } catch (Throwable) {
                        $failures++;
                        $this->error('Notification retry failed for activity '.$activity->id.'.');
                    }
                }
            });
        $this->info('Notification recovery completed; failures: '.$failures.'.');

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
