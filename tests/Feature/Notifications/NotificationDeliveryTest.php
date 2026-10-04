<?php

namespace Tests\Feature\Notifications;

use App\Events\LifecycleNotificationRequested;
use App\Listeners\QueueCustomerLifecycleNotification;
use App\Models\Booking;
use App\Models\CustomerCommunication;
use App\Models\User;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\BookingRequestReceivedNotification;
use App\Notifications\Channels\CustomerDatabaseChannel;
use App\Notifications\Channels\CustomerMailChannel;
use App\Services\LifecycleNotificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class NotificationDeliveryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_database_delivery_replay_keeps_one_notification_and_preserves_read_state(): void
    {
        $communication = $this->communication();
        $customer = User::query()->findOrFail($communication->customer_id);
        $notification = new BookingRequestReceivedNotification($communication->id);
        app(CustomerDatabaseChannel::class)->send($customer, $notification);
        $stored = $customer->notifications()->sole();
        $stored->markAsRead();
        $readAt = $stored->fresh()->read_at->toISOString();

        app(CustomerDatabaseChannel::class)->send($customer, $notification);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame($readAt, $stored->fresh()->read_at->toISOString());
        $this->assertNotNull($communication->fresh()->database_delivered_at);
        $this->assertSame($communication->fresh()->payload, $stored->fresh()->data);
    }

    public function test_successful_email_delivery_replay_does_not_send_another_email(): void
    {
        $communication = $this->communication();
        $customer = User::query()->findOrFail($communication->customer_id);
        $notification = new BookingRequestReceivedNotification($communication->id);
        $this->mock(MailChannel::class)->shouldReceive('send')->once()->andReturn($this->sentMessage());

        app(CustomerMailChannel::class)->send($customer, $notification);
        app(CustomerMailChannel::class)->send($customer, $notification);

        $this->assertNotNull($communication->fresh()->mail_delivered_at);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_failed_email_keeps_in_app_notification_and_remains_retryable(): void
    {
        $communication = $this->communication();
        $customer = User::query()->findOrFail($communication->customer_id);
        $notification = new BookingRequestReceivedNotification($communication->id);
        app(CustomerDatabaseChannel::class)->send($customer, $notification);
        $this->mock(MailChannel::class)->shouldReceive('send')->once()->andThrow(new RuntimeException('Mail provider unavailable'));

        try {
            app(CustomerMailChannel::class)->send($customer, $notification);
            $this->fail('A failed provider delivery must remain retryable.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Mail provider unavailable', $exception->getMessage());
        }

        $this->assertDatabaseCount('notifications', 1);
        $this->assertNotNull($communication->fresh()->database_delivered_at);
        $this->assertNull($communication->fresh()->mail_delivered_at);
        $this->mock(MailChannel::class)->shouldReceive('send')->once()->andReturn($this->sentMessage());
        app(CustomerMailChannel::class)->send($customer, $notification);
        $this->assertNotNull($communication->fresh()->mail_delivered_at);
    }

    public function test_email_rendering_escapes_customer_content_and_contains_the_authoritative_action(): void
    {
        $communication = $this->communication();
        $communication->update(['payload' => [...$communication->payload, 'body' => 'Request <script>alert(1)</script> received.']]);
        $customer = User::query()->findOrFail($communication->customer_id);

        $rendered = (string) (new BookingRequestReceivedNotification($communication->id))->toMail($customer)->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $rendered);
        $this->assertStringContainsString('&lt;script&gt;', $rendered);
        $this->assertStringContainsString('View bookings', $rendered);
        $this->assertStringContainsString('/bookings', $rendered);
    }

    public function test_replayed_activity_queues_separate_redis_channel_jobs_with_one_communication(): void
    {
        $booking = Booking::factory()->create();
        $activity = activity('booking')->performedOn($booking)->event('booking.requested')->log('Booking request persisted');
        config(['notifications.queue_connection' => 'redis']);
        Queue::fake([SendQueuedNotifications::class]);

        app(LifecycleNotificationService::class)->queueActivity($activity->id);
        app(LifecycleNotificationService::class)->queueActivity($activity->id);

        $this->assertDatabaseCount('customer_communications', 1);
        Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->connection === 'redis' && $job->queue === 'notifications' && count($job->channels) === 1);
        $this->assertDatabaseCount('notifications', 0);
    }

    /** @param class-string<CustomerDatabaseChannel>|class-string<CustomerMailChannel> $channel */
    #[TestWith([CustomerDatabaseChannel::class])]
    #[TestWith([CustomerMailChannel::class])]
    public function test_wrong_recipient_cannot_receive_another_customers_communication(string $channel): void
    {
        $communication = $this->communication();
        $other = User::factory()->create();
        $notification = new BookingRequestReceivedNotification($communication->id);
        $this->mock(MailChannel::class)->shouldNotReceive('send');

        try {
            app($channel)->send($other, $notification);
        } catch (ModelNotFoundException|AuthorizationException $exception) {
            $this->assertNotNull($exception);
        }

        $this->assertDatabaseCount('notifications', 0);
        $this->assertNull($communication->fresh()->database_delivered_at);
        $this->assertNull($communication->fresh()->mail_delivered_at);
    }

    public function test_delivery_uses_frozen_communication_instead_of_later_booking_state(): void
    {
        $communication = $this->communication();
        $booking = Booking::query()->where('customer_id', $communication->customer_id)->sole();
        $booking->update(['reference' => 'BKG-LATER-CHANGE']);
        $customer = User::query()->findOrFail($communication->customer_id);

        app(CustomerDatabaseChannel::class)->send($customer, new BookingRequestReceivedNotification($communication->id));

        $this->assertSame($communication->fresh()->payload, $customer->notifications()->sole()->data);
        $this->assertStringNotContainsString('BKG-LATER-CHANGE', $customer->notifications()->sole()->data['body']);
    }

    public function test_queue_failure_preserves_committed_audit_and_communication_for_recovery(): void
    {
        $booking = Booking::factory()->create();
        $activity = activity('booking')->performedOn($booking)->event('booking.requested')->log('Booking request persisted');
        config(['notifications.queue_connection' => 'redis']);
        Queue::shouldReceive('connection')->with('redis')->andThrow(new RuntimeException('Redis unavailable'));

        app(QueueCustomerLifecycleNotification::class)->handle(new LifecycleNotificationRequested($activity->id));

        $this->assertModelExists($booking);
        $this->assertModelExists($activity);
        $this->assertDatabaseCount('customer_communications', 1);
        $this->assertDatabaseCount('notifications', 0);
        Queue::fake([SendQueuedNotifications::class]);
        $this->artisan('notifications:retry')->assertExitCode(0);
        Queue::assertPushed(SendQueuedNotifications::class, 2);
        $this->assertDatabaseCount('customer_communications', 1);
    }

    public function test_recovery_does_not_backfill_historical_events_before_the_rollout_checkpoint(): void
    {
        $booking = Booking::factory()->create();
        $historical = activity('booking')->performedOn($booking)->event('booking.requested')->log('Earlier booking request');
        DB::table('notification_delivery_checkpoints')->where('id', 1)->update(['first_activity_id' => $historical->id + 1]);
        Queue::fake([SendQueuedNotifications::class]);

        $communication = app(LifecycleNotificationService::class)->queueActivity($historical->id);
        $this->artisan('notifications:retry')->assertExitCode(0);

        $this->assertNull($communication);
        $this->assertDatabaseCount('customer_communications', 0);
        Queue::assertNothingPushed();
    }

    public function test_failed_communication_persistence_remains_recoverable_from_the_committed_audit(): void
    {
        $booking = Booking::factory()->create();
        $activity = activity('booking')->performedOn($booking)->event('booking.requested')->log('Booking request persisted');
        CustomerCommunication::creating(function (): void {
            throw new RuntimeException('Communication persistence unavailable');
        });
        try {
            app(QueueCustomerLifecycleNotification::class)->handle(new LifecycleNotificationRequested($activity->id));
        } finally {
            CustomerCommunication::flushEventListeners();
        }

        $this->assertModelExists($booking);
        $this->assertModelExists($activity);
        $this->assertDatabaseCount('customer_communications', 0);
        Queue::fake([SendQueuedNotifications::class]);
        $this->artisan('notifications:retry')->assertExitCode(0);
        $this->assertDatabaseCount('customer_communications', 1);
        Queue::assertPushed(SendQueuedNotifications::class, 2);
    }

    public function test_mail_not_accepted_for_delivery_does_not_mark_the_channel_complete(): void
    {
        $communication = $this->communication();
        $customer = User::query()->findOrFail($communication->customer_id);
        $this->mock(MailChannel::class)->shouldReceive('send')->once()->andReturnNull();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Notification email was not accepted for delivery.');
        try {
            app(CustomerMailChannel::class)->send($customer, new BookingRequestReceivedNotification($communication->id));
        } finally {
            $this->assertNull($communication->fresh()->mail_delivered_at);
        }
    }

    public function test_request_communication_cannot_be_sent_as_a_booking_confirmation(): void
    {
        $communication = $this->communication();
        $customer = User::query()->findOrFail($communication->customer_id);

        $this->expectException(AuthorizationException::class);
        try {
            app(CustomerDatabaseChannel::class)->send($customer, new BookingConfirmedNotification($communication->id));
        } finally {
            $this->assertDatabaseCount('notifications', 0);
            $this->assertNull($communication->fresh()->database_delivered_at);
        }
    }

    private function sentMessage(): SentMessage
    {
        $email = (new Email)->from('sender@example.test')->to('customer@example.test')->subject('Notification')->text('Test receipt');

        return new SentMessage(new \Symfony\Component\Mailer\SentMessage($email, Envelope::create($email)));
    }

    private function communication(): CustomerCommunication
    {
        $booking = Booking::factory()->create();
        $activity = activity('booking')->performedOn($booking)->event('booking.requested')->log('Booking request persisted');

        return CustomerCommunication::query()->create([
            'id' => (string) Str::uuid(), 'activity_id' => $activity->id,
            'semantic_key' => 'booking.requested:'.$booking->id,
            'customer_id' => $booking->customer_id, 'type' => 'booking.requested',
            'payload' => ['type' => 'booking.requested', 'title' => 'Booking request received',
                'body' => 'Your booking request is awaiting management review.', 'action_label' => 'View bookings',
                'action_url' => route('bookings.index', absolute: false), 'occurred_at' => now()->toISOString()],
        ]);
    }
}
