<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/**
 * Invites a customer whose account was created by staff to set their own password.
 *
 * The link uses the standard Fortify password reset flow. The token is created when
 * the email is built, so queue delays do not shorten the link's lifetime, and no
 * password is ever included in the message.
 */
class AssistedCustomerWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct()
    {
        $this->onConnection((string) config('notifications.queue_connection'));
        $this->onQueue((string) config('notifications.queue'));
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $broker = (string) config('fortify.passwords', config('auth.defaults.passwords'));
        $token = Password::broker($broker)->createToken($notifiable);
        $expiresInMinutes = (int) config("auth.passwords.{$broker}.expire", 60);

        return (new MailMessage)
            ->subject('Your '.config('app.name').' account')
            ->greeting("Hello {$notifiable->name},")
            ->line('Our team has created a '.config('app.name').' account for you while taking your phone or walk-in booking.')
            ->line('Set a password to view your bookings, invoices and updates online.')
            ->action('Set your password', route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]))
            ->line("This link expires in {$expiresInMinutes} minutes. If it has expired, use “Forgot password” on the sign-in page to get a new one.")
            ->line('If you did not make a booking with us, you can ignore this email.');
    }
}
