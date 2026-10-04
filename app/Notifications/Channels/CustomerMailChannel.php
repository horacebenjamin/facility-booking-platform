<?php

namespace App\Notifications\Channels;

use App\Models\CustomerCommunication;
use App\Models\User;
use App\Notifications\CustomerLifecycleNotification;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CustomerMailChannel
{
    public function __construct(private MailChannel $mail) {}

    public function send(User $notifiable, CustomerLifecycleNotification $notification): void
    {
        DB::transaction(function () use ($notifiable, $notification): void {
            $communication = CustomerCommunication::query()->lockForUpdate()->findOrFail($notification->communicationId);
            $notification->communication($notifiable);
            if ($communication->mail_delivered_at !== null) {
                return;
            }
            if ($this->mail->send($notifiable, $notification) === null) {
                throw new RuntimeException('Notification email was not accepted for delivery.');
            }
            $communication->update(['mail_delivered_at' => now()]);
        });
    }
}
