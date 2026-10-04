<?php

namespace App\Notifications\Channels;

use App\Models\CustomerCommunication;
use App\Models\User;
use App\Notifications\CustomerLifecycleNotification;
use Illuminate\Support\Facades\DB;

class CustomerDatabaseChannel
{
    public function send(User $notifiable, CustomerLifecycleNotification $notification): void
    {
        DB::transaction(function () use ($notifiable, $notification): void {
            $communication = CustomerCommunication::query()->lockForUpdate()->findOrFail($notification->communicationId);
            $notification->communication($notifiable);
            if ($communication->database_delivered_at !== null) {
                return;
            }
            $notifiable->notifications()->firstOrCreate(['id' => $communication->id], [
                'communication_id' => $communication->id, 'type' => $notification::class, 'data' => $communication->payload,
            ]);
            $communication->update(['database_delivered_at' => now()]);
        });
    }
}
