<?php

namespace App\Notifications;

use App\Models\CustomerCommunication;
use App\Models\User;
use App\Notifications\Channels\CustomerDatabaseChannel;
use App\Notifications\Channels\CustomerMailChannel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

abstract class CustomerLifecycleNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const Type = '';

    public int $tries = 5;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public string $communicationId)
    {
        $this->id = $communicationId;
        $this->onConnection((string) config('notifications.queue_connection'));
        $this->onQueue((string) config('notifications.queue'));
        $this->afterCommit();
    }

    /** @return list<class-string> */
    public function via(User $notifiable): array
    {
        $communication = $this->communication($notifiable);
        $channels = [];
        if ($communication->database_delivered_at === null) {
            $channels[] = CustomerDatabaseChannel::class;
        }
        if ($communication->mail_delivered_at === null) {
            $channels[] = CustomerMailChannel::class;
        }

        return $channels;
    }

    public function communication(User $notifiable): CustomerCommunication
    {
        $communication = CustomerCommunication::query()->findOrFail($this->communicationId);
        if ($communication->customer_id !== $notifiable->id || $communication->type !== static::Type) {
            throw new AuthorizationException;
        }

        return $communication;
    }

    /** @return array{type: string, title: string, body: string, action_label: string, action_url: string, occurred_at: string} */
    public function toDatabase(User $notifiable): array
    {
        return $this->communication($notifiable)->payload;
    }

    public function toMail(User $notifiable): MailMessage
    {
        $payload = $this->toDatabase($notifiable);

        return (new MailMessage)->subject($payload['title'])->line($payload['body'])
            ->action($payload['action_label'], url($payload['action_url']))
            ->withSymfonyMessage(function (Email $email): void {
                $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
                $email->getHeaders()->addIdHeader('Message-ID', $this->communicationId.'@'.$host);
            });
    }
}
