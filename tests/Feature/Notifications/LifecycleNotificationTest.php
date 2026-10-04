<?php

namespace Tests\Feature\Notifications;

use App\Actions\ApproveBooking;
use App\Actions\CreateBookingRequest;
use App\Actions\CreateRecurringBookingRequest;
use App\Actions\IssueInvoice;
use App\Actions\RecordManualInvoicePayment;
use App\Actions\RejectBooking;
use App\Actions\SetCustomerInvoiceTerms;
use App\Enums\BookingStatus;
use App\Enums\FinancialStatus;
use App\Enums\PaymentStatus;
use App\Enums\RecurrenceFrequency;
use App\Exceptions\InvoiceUnavailable;
use App\Models\AllocationUnit;
use App\Models\Booking;
use App\Models\CustomerCommunication;
use App\Models\FacilityBookableHour;
use App\Models\Payment;
use App\Models\Resource;
use App\Models\ResourceBookableHour;
use App\Models\ResourceRate;
use App\Models\User;
use App\Services\LifecycleNotificationService;
use App\Services\RecurrencePattern;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class LifecycleNotificationTest extends TestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    protected array $exceptTables = ['notification_delivery_checkpoints'];

    private const WebhookSecret = 'whsec_notification_test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemRoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00'));
        config(['cashier.webhook.secret' => self::WebhookSecret, 'payments.stripe_live_mode' => false]);
    }

    protected function tearDown(): void
    {
        try {
            $this->truncateTablesForAllConnections();
        } finally {
            parent::tearDown();
        }
    }

    public function test_request_received_communication_targets_the_actual_customer_after_commit(): void
    {
        Queue::fake();

        $booking = $this->requestBooking();

        $communication = CustomerCommunication::query()->sole();
        $this->assertSame('booking.requested', $communication->type);
        $this->assertSame($booking->customer_id, $communication->customer_id);
        $this->assertStringContainsString($booking->reference, $communication->payload['body']);
        $this->assertStringContainsString('review', strtolower($communication->payload['body']));
        $this->assertSame(BookingStatus::Requested, $booking->fresh()->status);
        $this->assertSame(FinancialStatus::NotDue, $booking->fresh()->financial_status);
        $this->assertSame('booking.requested', Activity::findOrFail($communication->activity_id)->event);
    }

    public function test_card_approval_requires_payment_without_premature_confirmation(): void
    {
        Queue::fake();
        $booking = $this->requestBooking();

        $approved = app(ApproveBooking::class)->handle($this->manager($booking), $booking);

        $this->assertSame(BookingStatus::Approved, $approved->status);
        $this->assertSame(FinancialStatus::AwaitingPayment, $approved->financial_status);
        $communication = CustomerCommunication::query()->where('type', 'booking.approved_payment_required')->sole();
        $this->assertSame($booking->customer_id, $communication->customer_id);
        $this->assertSame(route('bookings.payment.show', $booking, absolute: false), $communication->payload['action_url']);
        $this->assertStringContainsString('payment', strtolower($communication->payload['body']));
        $this->assertSame(0, CustomerCommunication::query()->where('type', 'booking.confirmed')->count());
    }

    public function test_invoice_approval_confirms_outstanding_booking_without_card_payment_prompt(): void
    {
        Queue::fake();
        $booking = $this->requestBooking();
        $manager = $this->manager($booking);
        app(SetCustomerInvoiceTerms::class)->handle($manager, $booking, true, 30);

        $approved = app(ApproveBooking::class)->handle($manager, $booking);

        $this->assertSame(BookingStatus::Confirmed, $approved->status);
        $this->assertSame(FinancialStatus::InvoiceOutstanding, $approved->financial_status);
        $this->assertSame(0, CustomerCommunication::query()->where('type', 'booking.approved_payment_required')->count());
        $communication = CustomerCommunication::query()->where('type', 'booking.confirmed')->sole();
        $this->assertSame($booking->customer_id, $communication->customer_id);
        $this->assertStringContainsString('outstanding', strtolower($communication->payload['body']));
        $this->assertSame(0, CustomerCommunication::query()->where('type', 'payment.received')->count());
    }

    public function test_rejection_includes_validated_customer_reason_without_confirmation(): void
    {
        Queue::fake();
        $booking = $this->requestBooking();

        app(RejectBooking::class)->handle($this->manager($booking), $booking, 'The requested session cannot be accommodated.');

        $communication = CustomerCommunication::query()->where('type', 'booking.rejected')->sole();
        $this->assertSame($booking->customer_id, $communication->customer_id);
        $this->assertStringContainsString('The requested session cannot be accommodated.', $communication->payload['body']);
        $this->assertSame(BookingStatus::Rejected, $booking->fresh()->status);
        $this->assertSame(0, CustomerCommunication::query()->where('type', 'booking.confirmed')->count());
    }

    public function test_verified_stripe_success_receipt_and_confirmation_are_exactly_once(): void
    {
        Queue::fake();
        $payment = $this->stripePayment();
        $event = $this->providerEvent($payment);

        $this->deliver($event)->assertOk();
        $this->deliver($event)->assertOk();
        $event['id'] = 'evt_distinct_success_same_payment';
        $this->deliver($event)->assertOk();

        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $payment->booking->fresh()->status);
        $this->assertSame(FinancialStatus::Paid, $payment->booking->fresh()->financial_status);
        $receipt = CustomerCommunication::query()->where('type', 'payment.received')->sole();
        $confirmation = CustomerCommunication::query()->where('type', 'booking.confirmed')->sole();
        $this->assertSame($payment->customer_id, $receipt->customer_id);
        $this->assertSame($payment->customer_id, $confirmation->customer_id);
        $this->assertStringNotContainsString('pi_test', json_encode($receipt->payload, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('cs_test', json_encode($receipt->payload, JSON_THROW_ON_ERROR));
    }

    #[TestWith(['checkout.session.completed', 'complete', 'processing'])]
    #[TestWith(['checkout.session.async_payment_failed', 'complete', 'failed'])]
    #[TestWith(['checkout.session.expired', 'expired', 'expired'])]
    public function test_non_success_provider_outcomes_do_not_send_receipt_or_confirmation(string $type, string $sessionStatus, string $expectedPaymentStatus): void
    {
        Queue::fake();
        $payment = $this->stripePayment();
        $event = $this->providerEvent($payment);
        $event['type'] = $type;
        $event['data']['object']['status'] = $sessionStatus;
        $event['data']['object']['payment_status'] = 'unpaid';

        $this->deliver($event)->assertOk();

        $this->assertSame($expectedPaymentStatus, $payment->fresh()->status->value);
        $this->assertSame(BookingStatus::Approved, $payment->booking->fresh()->status);
        $this->assertSame(0, CustomerCommunication::query()->whereIn('type', ['payment.received', 'booking.confirmed'])->count());
    }

    public function test_received_money_requiring_review_does_not_claim_booking_confirmation(): void
    {
        Queue::fake();
        $payment = $this->stripePayment();
        $payment->booking->update(['payment_due_at' => now()->subMinute()]);

        $this->deliver($this->providerEvent($payment))->assertOk();

        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->reconciliation_issue);
        $this->assertSame(BookingStatus::Approved, $payment->booking->fresh()->status);
        $this->assertSame(1, CustomerCommunication::query()->where('type', 'payment.received')->count());
        $this->assertSame(0, CustomerCommunication::query()->where('type', 'booking.confirmed')->count());
    }

    public function test_invoice_issue_and_manual_settlement_notify_once_without_reconfirming_booking(): void
    {
        Queue::fake();
        $booking = $this->requestBooking();
        $manager = $this->manager($booking);
        app(SetCustomerInvoiceTerms::class)->handle($manager, $booking, true, 30);
        app(ApproveBooking::class)->handle($manager, $booking);
        $invoice = app(IssueInvoice::class)->handle($manager, [$booking->id]);

        try {
            app(IssueInvoice::class)->handle($manager, [$booking->id]);
            $this->fail('Repeated invoice issue must be rejected.');
        } catch (InvoiceUnavailable) {
            $this->assertSame(1, CustomerCommunication::query()->where('type', 'invoice.issued')->count());
        }
        $payment = app(RecordManualInvoicePayment::class)->handle($manager, $invoice, 'BANK-VERIFIED', 'Externally verified receipt.');
        try {
            app(RecordManualInvoicePayment::class)->handle($manager, $invoice, 'BANK-VERIFIED', 'Externally verified receipt.');
            $this->fail('Repeated settlement must be rejected.');
        } catch (InvoiceUnavailable) {
            $this->assertSame(1, CustomerCommunication::query()->where('type', 'payment.received')->count());
        }

        $issued = CustomerCommunication::query()->where('type', 'invoice.issued')->sole();
        $receipt = CustomerCommunication::query()->where('type', 'payment.received')->sole();
        $this->assertSame($booking->customer_id, $issued->customer_id);
        $this->assertSame(route('invoices.show', $invoice, absolute: false), $issued->payload['action_url']);
        $this->assertSame(route('invoices.show', $invoice, absolute: false), $receipt->payload['action_url']);
        $this->assertStringNotContainsString('Externally verified receipt.', json_encode($receipt->payload, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('BANK-VERIFIED', json_encode($receipt->payload, JSON_THROW_ON_ERROR));
        $this->assertSame(1, CustomerCommunication::query()->where('type', 'booking.confirmed')->count());
        $this->assertSame(FinancialStatus::Paid, $booking->fresh()->financial_status);
        $this->assertSame(PaymentStatus::Succeeded, $payment->status);
    }

    public function test_outer_transaction_commit_controls_request_notification_creation(): void
    {
        Queue::fake();
        DB::beginTransaction();
        $booking = $this->requestBooking();
        $this->assertSame(1, Booking::query()->count());
        $this->assertDatabaseCount('customer_communications', 0);

        DB::commit();

        $this->assertSame($booking->customer_id, CustomerCommunication::query()->sole()->customer_id);
    }

    public function test_outer_transaction_rollback_discards_request_notification(): void
    {
        Queue::fake();
        DB::beginTransaction();
        $this->requestBooking();
        $this->assertDatabaseCount('customer_communications', 0);

        DB::rollBack();

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('customer_communications', 0);
        Queue::assertNothingPushed();
    }

    public function test_notification_service_failure_does_not_rollback_approval_and_audit(): void
    {
        Queue::fake();
        $booking = $this->requestBooking();
        $this->mock(LifecycleNotificationService::class)->shouldReceive('queueActivity')->once()
            ->andThrow(new RuntimeException('Notification queue unavailable.'));

        $approved = app(ApproveBooking::class)->handle($this->manager($booking), $booking);

        $this->assertSame(BookingStatus::Approved, $approved->status);
        $this->assertSame(FinancialStatus::AwaitingPayment, $booking->fresh()->financial_status);
        $this->assertSame(1, $booking->activities()->where('event', 'booking.approved')->count());
    }

    public function test_notification_service_failure_does_not_rollback_booking_request(): void
    {
        Queue::fake();
        $this->mock(LifecycleNotificationService::class)->shouldReceive('queueActivity')->once()
            ->andThrow(new RuntimeException('Notification queue unavailable.'));

        $booking = $this->requestBooking();

        $this->assertModelExists($booking);
        $this->assertSame(BookingStatus::Requested, $booking->fresh()->status);
        $this->assertSame(1, $booking->activities()->where('event', 'booking.requested')->count());
    }

    public function test_notification_service_failure_does_not_rollback_verified_payment_or_confirmation(): void
    {
        Queue::fake();
        $payment = $this->stripePayment();
        $this->mock(LifecycleNotificationService::class)->shouldReceive('queueActivity')->twice()
            ->andThrow(new RuntimeException('Notification queue unavailable.'));

        $this->deliver($this->providerEvent($payment))->assertOk();

        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $payment->booking->fresh()->status);
        $this->assertSame(FinancialStatus::Paid, $payment->booking->fresh()->financial_status);
        $this->assertSame(1, $payment->booking->activities()->where('event', 'booking.confirmed')->count());
    }

    public function test_notification_service_failure_does_not_rollback_manual_invoice_settlement(): void
    {
        Queue::fake();
        $booking = $this->requestBooking();
        $manager = $this->manager($booking);
        app(SetCustomerInvoiceTerms::class)->handle($manager, $booking, true, 30);
        app(ApproveBooking::class)->handle($manager, $booking);
        $invoice = app(IssueInvoice::class)->handle($manager, [$booking->id]);
        $this->mock(LifecycleNotificationService::class)->shouldReceive('queueActivity')->once()
            ->andThrow(new RuntimeException('Notification queue unavailable.'));

        $payment = app(RecordManualInvoicePayment::class)->handle($manager, $invoice, 'BANK-VERIFIED', 'Externally verified receipt.');

        $this->assertSame(PaymentStatus::Succeeded, $payment->status);
        $this->assertSame('paid', $invoice->fresh()->status->value);
        $this->assertSame(FinancialStatus::Paid, $booking->fresh()->financial_status);
        $this->assertSame(1, $invoice->activities()->where('event', 'invoice.paid')->count());
    }

    public function test_notification_service_failure_does_not_rollback_rejection(): void
    {
        Queue::fake();
        $booking = $this->requestBooking();
        $this->mock(LifecycleNotificationService::class)->shouldReceive('queueActivity')->once()
            ->andThrow(new RuntimeException('Notification queue unavailable.'));

        app(RejectBooking::class)->handle($this->manager($booking), $booking, 'No suitable session is available.');

        $this->assertSame(BookingStatus::Rejected, $booking->fresh()->status);
        $this->assertSame(1, $booking->activities()->where('event', 'booking.rejected')->count());
        $this->assertNull($booking->fresh()->allocationOccupancy);
    }

    public function test_notification_service_failure_does_not_rollback_invoice_issue(): void
    {
        Queue::fake();
        $booking = $this->requestBooking();
        $manager = $this->manager($booking);
        app(SetCustomerInvoiceTerms::class)->handle($manager, $booking, true, 30);
        app(ApproveBooking::class)->handle($manager, $booking);
        $this->mock(LifecycleNotificationService::class)->shouldReceive('queueActivity')->once()
            ->andThrow(new RuntimeException('Notification queue unavailable.'));

        $invoice = app(IssueInvoice::class)->handle($manager, [$booking->id]);

        $this->assertModelExists($invoice);
        $this->assertSame('issued', $invoice->status->value);
        $this->assertSame(FinancialStatus::Invoiced, $booking->fresh()->financial_status);
        $this->assertSame(1, $invoice->activities()->where('event', 'invoice.issued')->count());
    }

    public function test_mail_transport_failure_preserves_committed_request_and_in_app_notification(): void
    {
        config(['notifications.queue_connection' => 'sync']);
        $this->mock(MailChannel::class)->shouldReceive('send')->once()
            ->andThrow(new RuntimeException('Simulated SMTP delivery failure.'));

        $booking = $this->requestBooking();

        $this->assertModelExists($booking);
        $this->assertSame(BookingStatus::Requested, $booking->fresh()->status);
        $this->assertSame(1, $booking->activities()->where('event', 'booking.requested')->count());
        $communication = CustomerCommunication::query()->sole();
        $this->assertNotNull($communication->database_delivered_at);
        $this->assertNull($communication->mail_delivered_at);
        $notification = $booking->customer->notifications()->sole();
        $this->assertSame($communication->id, $notification->id);
        $this->assertStringContainsString($booking->reference, $notification->data['body']);
    }

    public function test_recurring_requests_preserve_distinct_occurrence_references(): void
    {
        Queue::fake();
        $resource = $this->bookableResource();
        $customer = $this->customer();

        $result = app(CreateRecurringBookingRequest::class)->handle($customer, $resource->id,
            CarbonImmutable::parse('2026-10-05 18:00:00'), CarbonImmutable::parse('2026-10-05 20:00:00'),
            new RecurrencePattern(RecurrenceFrequency::Weekly, 1, 3, 'Europe/London'));

        $this->assertTrue($result->wasCreated());
        $this->assertCount(3, $result->bookings);
        $this->assertSame(3, CustomerCommunication::query()->where('type', 'booking.requested')->count());
        foreach ($result->bookings as $booking) {
            $communication = CustomerCommunication::query()->where('activity_id', $booking->activities()->where('event', 'booking.requested')->sole()->id)->sole();
            $this->assertSame($customer->id, $communication->customer_id);
            $this->assertStringContainsString($booking->reference, $communication->payload['body']);
            $this->assertNotNull($booking->occurrence_index);
        }
    }

    private function requestBooking(): Booking
    {
        return app(CreateBookingRequest::class)->handle($this->customer(), $this->bookableResource()->id,
            CarbonImmutable::parse('2026-10-05 18:00:00'), CarbonImmutable::parse('2026-10-05 20:00:00'));
    }

    private function bookableResource(): Resource
    {
        $resource = Resource::factory()->create(['setup_minutes' => 0, 'cleanup_minutes' => 0]);
        $resource->syncAllocationUnits(AllocationUnit::factory()->for($resource->facility)->create());
        FacilityBookableHour::factory()->for($resource->facility)->create([
            'day_of_week' => 1, 'opens_at' => '08:00:00', 'closes_at' => '22:00:00',
        ]);
        ResourceBookableHour::factory()->for($resource)->create([
            'day_of_week' => 1, 'opens_at' => '08:00:00', 'closes_at' => '22:00:00',
        ]);
        ResourceRate::factory()->for($resource)->create(['amount_minor' => 2500]);

        return $resource;
    }

    private function customer(): User
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        return $customer;
    }

    private function manager(Booking $booking): User
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->assignedCentres()->attach($booking->centre_id);

        return $manager;
    }

    private function stripePayment(): Payment
    {
        $booking = $this->requestBooking();
        $booking = app(ApproveBooking::class)->handle($this->manager($booking), $booking);

        return Payment::factory()->for($booking)->create([
            'customer_id' => $booking->customer_id, 'provider' => 'stripe', 'provider_session_id' => 'cs_test',
            'provider_payment_intent_id' => 'pi_test', 'amount_minor' => 5000, 'currency' => 'GBP',
            'status' => PaymentStatus::Pending, 'session_expires_at' => now()->addDay(),
        ]);
    }

    /** @return array<string, mixed> */
    private function providerEvent(Payment $payment): array
    {
        return [
            'id' => 'evt_notification_test', 'object' => 'event', 'type' => 'checkout.session.completed',
            'created' => now()->timestamp, 'livemode' => false,
            'data' => ['object' => [
                'id' => $payment->provider_session_id, 'object' => 'checkout.session', 'mode' => 'payment',
                'livemode' => false, 'status' => 'complete', 'payment_status' => 'paid',
                'amount_total' => $payment->amount_minor, 'currency' => strtolower($payment->currency),
                'payment_intent' => 'pi_test', 'client_reference_id' => $payment->reference,
                'metadata' => ['payment_reference' => $payment->reference],
            ]],
        ];
    }

    /** @param array<string, mixed> $event */
    private function deliver(array $event): TestResponse
    {
        $body = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, self::WebhookSecret);

        return $this->call('POST', route('payments.stripe.webhook'), server: [
            'CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], content: $body);
    }
}
