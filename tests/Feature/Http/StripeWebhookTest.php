<?php

namespace Tests\Feature\Http;

use App\Actions\ApproveBooking;
use App\Actions\CreateBookingRequest;
use App\Enums\BookingStatus;
use App\Enums\DayOfWeek;
use App\Enums\FinancialStatus;
use App\Enums\PaymentStatus;
use App\Models\AllocationOccupancy;
use App\Models\AllocationUnit;
use App\Models\Booking;
use App\Models\BookingPriceLine;
use App\Models\BookingPriceSnapshot;
use App\Models\BookingSeries;
use App\Models\Facility;
use App\Models\FacilityBookableHour;
use App\Models\Payment;
use App\Models\Resource;
use App\Models\ResourceBookableHour;
use App\Models\ResourceRate;
use App\Models\User;
use App\Services\OperationalOccupancyCalculator;
use App\Services\PaymentService;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const WebhookSecret = 'whsec_facility4hire_test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemRoleSeeder::class);
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(12, 0));
        config(['cashier.webhook.secret' => self::WebhookSecret, 'payments.stripe_live_mode' => false]);
    }

    public function test_verified_paid_session_reconciles_payment_booking_and_audit_once(): void
    {
        $payment = $this->payment();
        $snapshot = $payment->booking->priceSnapshot->getAttributes();
        $event = $this->event($payment);

        $this->deliver($event)->assertOk();
        $this->deliver($event)->assertOk();
        $event['id'] = 'evt_second_success';
        $this->deliver($event)->assertOk();

        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->succeeded_at);
        $this->assertSame(BookingStatus::Confirmed, $payment->booking->fresh()->status);
        $this->assertSame(FinancialStatus::Paid, $payment->booking->fresh()->financial_status);
        $this->assertSame($snapshot, $payment->booking->priceSnapshot->fresh()->getAttributes());
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1, Activity::query()->where('subject_type', Payment::class)->where('subject_id', $payment->id)->where('event', 'payment.succeeded')->count());
        $this->assertSame(1, $payment->booking->activities()->where('event', 'booking.confirmed')->count());
    }

    #[TestWith(['checkout.session.completed', 'unpaid', 'processing'])]
    #[TestWith(['checkout.session.async_payment_failed', 'unpaid', 'failed'])]
    #[TestWith(['checkout.session.expired', 'unpaid', 'expired'])]
    public function test_non_success_outcome_does_not_confirm_booking(string $type, string $paymentStatus, string $expected): void
    {
        $payment = $this->payment();
        $event = $this->event($payment, $type);
        $event['data']['object']['payment_status'] = $paymentStatus;
        $event['data']['object']['status'] = $type === 'checkout.session.expired' ? 'expired' : 'complete';
        $this->deliver($event)->assertOk();
        $this->assertSame($expected, $payment->fresh()->status->value);
        $this->assertSame(BookingStatus::Approved, $payment->booking->fresh()->status);
        $this->assertSame(FinancialStatus::AwaitingPayment, $payment->booking->fresh()->financial_status);
        $this->assertSame(0, $payment->booking->activities()->where('event', 'booking.confirmed')->count());
    }

    public function test_invalid_signature_is_rejected_without_financial_effect(): void
    {
        $payment = $this->payment();
        $body = json_encode($this->event($payment), JSON_THROW_ON_ERROR);
        $this->call('POST', route('payments.stripe.webhook'), server: ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=invalid'], content: $body)
            ->assertForbidden();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame(BookingStatus::Approved, $payment->booking->fresh()->status);
    }

    public function test_missing_configured_webhook_secret_fails_closed(): void
    {
        $payment = $this->payment();
        config(['cashier.webhook.secret' => null]);
        $this->deliver($this->event($payment))->assertStatus(503);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    #[TestWith(['amount_total', 4999])]
    #[TestWith(['amount_total', '5000'])]
    #[TestWith(['currency', 'usd'])]
    #[TestWith(['payment_intent', 'pi_wrong'])]
    #[TestWith(['client_reference_id', 'wrong'])]
    #[TestWith(['livemode', true])]
    #[TestWith(['mode', 'subscription'])]
    #[TestWith(['id', 'cs_wrong'])]
    #[TestWith(['object', 'payment_intent'])]
    public function test_mismatched_provider_context_cannot_confirm_booking(string $field, mixed $value): void
    {
        $payment = $this->payment();
        $event = $this->event($payment);
        $event['data']['object'][$field] = $value;
        $this->deliver($event)->assertUnprocessable();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame(BookingStatus::Approved, $payment->booking->fresh()->status);
        $this->assertSame(FinancialStatus::AwaitingPayment, $payment->booking->fresh()->financial_status);
    }

    public function test_mismatched_payment_metadata_cannot_confirm_booking(): void
    {
        $payment = $this->payment();
        $event = $this->event($payment);
        $event['data']['object']['metadata']['payment_reference'] = 'wrong';
        $this->deliver($event)->assertUnprocessable();
        $this->assertSame(BookingStatus::Approved, $payment->booking->fresh()->status);
    }

    public function test_missing_signature_is_rejected_without_mutation(): void
    {
        $payment = $this->payment();
        $this->call('POST', route('payments.stripe.webhook'), server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($this->event($payment), JSON_THROW_ON_ERROR))->assertForbidden();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    #[TestWith(['livemode', true])]
    #[TestWith(['account', 'acct_other'])]
    public function test_wrong_provider_environment_or_account_cannot_reconcile(string $field, mixed $value): void
    {
        $payment = $this->payment();
        $event = $this->event($payment);
        $event[$field] = $value;
        $this->deliver($event)->assertUnprocessable();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame(BookingStatus::Approved, $payment->booking->fresh()->status);
    }

    public function test_session_not_yet_bound_is_retryable_without_guessing_identity(): void
    {
        $payment = $this->payment();
        $event = $this->event($payment);
        $payment->update(['provider_session_id' => null]);
        $this->deliver($event)->assertStatus(503);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_unrelated_provider_event_is_ignored_without_state_changes(): void
    {
        $payment = $this->payment();
        $this->deliver($this->event($payment, 'customer.created'))->assertOk();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame(BookingStatus::Approved, $payment->booking->fresh()->status);
    }

    public function test_out_of_order_failure_does_not_downgrade_success(): void
    {
        $payment = $this->payment();
        $this->deliver($this->event($payment))->assertOk();
        $failed = $this->event($payment, 'checkout.session.async_payment_failed');
        $failed['id'] = 'evt_late_failure';
        $failed['data']['object']['payment_status'] = 'unpaid';
        $this->deliver($failed)->assertOk();
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $payment->booking->fresh()->status);
    }

    public function test_confirmation_failure_rolls_back_payment_and_audit_and_event_can_retry(): void
    {
        $payment = $this->payment();
        $listener = 'eloquent.updating: '.Booking::class;
        Event::listen($listener, function (Booking $booking) use ($payment): void {
            if ($booking->id === $payment->booking_id && $booking->status === BookingStatus::Confirmed) {
                throw new \RuntimeException('Simulated confirmation persistence failure');
            }
        });

        try {
            $this->deliver($this->event($payment))->assertServerError();
        } finally {
            Event::forget($listener);
        }

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame(BookingStatus::Approved, $payment->booking->fresh()->status);
        $this->assertSame(0, Activity::query()->where('event', 'payment.succeeded')->count());
        $this->assertSame(0, $payment->booking->activities()->where('event', 'booking.confirmed')->count());
        $this->deliver($this->event($payment))->assertOk();
        $this->assertSame(BookingStatus::Confirmed, $payment->booking->fresh()->status);
    }

    public function test_success_against_stale_booking_records_money_without_unsafe_confirmation(): void
    {
        $payment = $this->payment();
        $payment->booking->update(['status' => BookingStatus::Rejected]);
        $this->deliver($this->event($payment))->assertOk();
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->reconciliation_issue);
        $this->assertSame(BookingStatus::Rejected, $payment->booking->fresh()->status);
        $this->assertSame(FinancialStatus::AwaitingPayment, $payment->booking->fresh()->financial_status);
    }

    public function test_received_payment_without_protection_requires_review_and_does_not_confirm(): void
    {
        $payment = $this->payment();
        $payment->booking->allocationOccupancy()->sole()->allocationUnits()->detach();
        $payment->booking->allocationOccupancy()->delete();
        $this->deliver($this->event($payment))->assertOk();
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->reconciliation_issue);
        $this->assertSame(BookingStatus::Approved, $payment->booking->fresh()->status);
        $this->assertSame(FinancialStatus::AwaitingPayment, $payment->booking->fresh()->financial_status);
    }

    public function test_paid_provider_event_after_session_deadline_requires_review(): void
    {
        $payment = $this->payment();
        $payment->update(['session_expires_at' => now()->subMinute()]);
        $this->deliver($this->event($payment))->assertOk();
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->reconciliation_issue);
        $this->assertSame(BookingStatus::Approved, $payment->booking->fresh()->status);
    }

    public function test_timely_success_delivered_after_deadline_still_confirms(): void
    {
        $payment = $this->payment();
        $event = $this->event($payment);
        $this->travelTo(now()->addDays(2));
        $this->deliver($event)->assertOk();
        $this->assertSame(BookingStatus::Confirmed, $payment->booking->fresh()->status);
    }

    public function test_one_recurring_occurrence_can_pay_without_changing_siblings(): void
    {
        $payment = $this->payment();
        $booking = $payment->booking;
        $series = BookingSeries::factory()->create([
            'customer_id' => $booking->customer_id, 'centre_id' => $booking->centre_id,
            'facility_id' => $booking->facility_id, 'resource_id' => $booking->resource_id,
            'first_starts_at' => $booking->starts_at, 'first_ends_at' => $booking->ends_at,
        ]);
        $booking->update(['booking_series_id' => $series->id, 'occurrence_index' => 1]);
        $sibling = Booking::factory()->create([
            'customer_id' => $booking->customer_id, 'centre_id' => $booking->centre_id,
            'facility_id' => $booking->facility_id, 'resource_id' => $booking->resource_id,
            'booking_series_id' => $series->id, 'occurrence_index' => 2,
        ]);
        $siblingBefore = $sibling->fresh()->getAttributes();
        $this->deliver($this->event($payment))->assertOk();
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertSame($siblingBefore, $sibling->fresh()->getAttributes());
        $this->assertSame($series->id, $booking->fresh()->booking_series_id);
        $this->assertSame(1, $booking->fresh()->occurrence_index);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_request_approval_payment_and_verified_confirmation_work_end_to_end(): void
    {
        $facility = Facility::factory()->create();
        $resource = Resource::factory()->for($facility)->create();
        $unit = AllocationUnit::factory()->for($facility)->create();
        $resource->syncAllocationUnits($unit);
        FacilityBookableHour::factory()->for($facility)->create(['day_of_week' => DayOfWeek::Monday, 'opens_at' => '08:00:00', 'closes_at' => '22:00:00']);
        ResourceBookableHour::factory()->for($resource)->create(['day_of_week' => DayOfWeek::Monday, 'opens_at' => '08:00:00', 'closes_at' => '22:00:00']);
        ResourceRate::factory()->for($resource)->create();
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->assignedCentres()->attach($facility->centre);
        $booking = app(CreateBookingRequest::class)->handle($customer, $resource->id,
            CarbonImmutable::parse('2026-10-05 18:00:00', config('app.timezone')),
            CarbonImmutable::parse('2026-10-05 20:00:00', config('app.timezone')), []);
        $booking = app(ApproveBooking::class)->handle($manager, $booking);
        $this->assertNotNull($booking->payment_due_at);
        $this->assertSame(BookingStatus::Approved, $booking->status);
        $this->mock(PaymentService::class)->shouldReceive('createCheckout')->once()->andReturn([
            'id' => 'cs_test', 'url' => 'https://checkout.stripe.com/c/pay_test', 'payment_intent' => 'pi_test', 'livemode' => false,
        ]);
        $this->actingAs($customer)->postJson(route('bookings.payment.store', $booking))->assertSuccessful();
        $payment = Payment::query()->sole();
        $this->deliver($this->event($payment))->assertOk();
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertSame(FinancialStatus::Paid, $booking->fresh()->financial_status);
        $this->assertTrue($booking->allocationOccupancy()->exists());
        $this->assertNull($booking->allocationOccupancy()->sole()->expires_at);
    }

    private function payment(): Payment
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        $booking = Booking::factory()->create([
            'customer_id' => $customer->id, 'status' => BookingStatus::Approved,
            'financial_status' => FinancialStatus::AwaitingPayment, 'payment_due_at' => now()->addDay(),
            'starts_at' => now()->addDays(4), 'ends_at' => now()->addDays(4)->addHours(2),
        ]);
        $snapshot = BookingPriceSnapshot::factory()->for($booking)->create();
        BookingPriceLine::factory()->for($snapshot, 'snapshot')->create();
        $unit = AllocationUnit::factory()->for($booking->resource->facility)->create();
        $booking->resource->syncAllocationUnits($unit);
        $period = app(OperationalOccupancyCalculator::class)->calculate($booking->resource, $booking->starts_at, $booking->ends_at);
        $this->assertNotNull($period);
        $occupancy = AllocationOccupancy::factory()->for($booking)->create([
            'starts_at' => $period->startsAt, 'ends_at' => $period->endsAt, 'expires_at' => null,
        ]);
        $occupancy->allocationUnits()->attach($unit);

        return Payment::factory()->for($booking)->create([
            'customer_id' => $customer->id, 'provider' => 'stripe', 'provider_session_id' => 'cs_test',
            'provider_payment_intent_id' => 'pi_test', 'amount_minor' => 5000, 'currency' => 'GBP',
            'status' => PaymentStatus::Pending, 'session_expires_at' => now()->addDay(),
        ]);
    }

    /** @return array<string, mixed> */
    private function event(Payment $payment, string $type = 'checkout.session.completed'): array
    {
        return [
            'id' => 'evt_test', 'object' => 'event', 'type' => $type,
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
