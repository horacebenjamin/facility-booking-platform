<?php

namespace Tests\Feature\Http;

use App\Enums\BookingStatus;
use App\Enums\FinancialStatus;
use App\Models\AllocationOccupancy;
use App\Models\AllocationUnit;
use App\Models\Booking;
use App\Models\BookingPriceLine;
use App\Models\BookingPriceSnapshot;
use App\Models\Payment;
use App\Models\User;
use App\Services\OperationalOccupancyCalculator;
use App\Services\PaymentService;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BookingPaymentTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemRoleSeeder::class);
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(12, 0));
        config(['payments.stripe_live_mode' => false]);
    }

    public function test_eligible_customer_initiates_payment_using_persisted_price_and_currency(): void
    {
        $booking = $this->booking();
        $this->mock(PaymentService::class)->shouldReceive('createCheckout')->once()
            ->withArgs(fn (Payment $payment): bool => $payment->booking_id === $booking->id
                && $payment->customer_id === $booking->customer_id
                && $payment->amount_minor === 5000 && $payment->currency === 'GBP')
            ->andReturn($this->checkout());

        $this->actingAs($booking->customer)->postJson(route('bookings.payment.store', $booking))
            ->assertSuccessful()->assertJsonPath('data.checkout_url', 'https://checkout.stripe.com/c/pay_test');

        $this->assertDatabaseHas('payments', ['booking_id' => $booking->id, 'amount_minor' => 5000, 'currency' => 'GBP', 'provider_session_id' => 'cs_test']);
        $this->assertSame(BookingStatus::Approved, $booking->fresh()->status);
        $this->assertSame(FinancialStatus::AwaitingPayment, $booking->fresh()->financial_status);
    }

    #[TestWith(['requested', 'not_due'])]
    #[TestWith(['rejected', 'not_due'])]
    #[TestWith(['confirmed', 'paid'])]
    #[TestWith(['approved', 'paid'])]
    public function test_ineligible_lifecycle_cannot_start_checkout(string $status, string $financialStatus): void
    {
        $booking = $this->booking(['status' => $status, 'financial_status' => $financialStatus]);
        $this->mock(PaymentService::class)->shouldNotReceive('createCheckout');
        $this->actingAs($booking->customer)->postJson(route('bookings.payment.store', $booking))->assertConflict();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_another_customer_cannot_view_or_pay_booking(): void
    {
        $booking = $this->booking();
        $other = User::factory()->create();
        $other->assignRole('customer');
        $this->mock(PaymentService::class)->shouldNotReceive('createCheckout');
        $this->actingAs($other)->get(route('bookings.payment.show', $booking))->assertNotFound();
        $this->postJson(route('bookings.payment.store', $booking))->assertNotFound();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_guest_cannot_initiate_payment(): void
    {
        $booking = $this->booking();
        $this->postJson(route('bookings.payment.store', $booking))->assertUnauthorized();
        $this->assertDatabaseCount('payments', 0);
    }

    #[TestWith(['amount_minor', 1])]
    #[TestWith(['currency', 'USD'])]
    #[TestWith(['status', 'succeeded'])]
    #[TestWith(['financial_status', 'paid'])]
    #[TestWith(['booking_status', 'confirmed'])]
    #[TestWith(['provider', 'offline'])]
    #[TestWith(['provider_session_id', 'cs_forged'])]
    public function test_browser_cannot_supply_financial_or_provider_state(string $key, mixed $value): void
    {
        $booking = $this->booking();
        $this->mock(PaymentService::class)->shouldNotReceive('createCheckout');
        $this->actingAs($booking->customer)->postJson(route('bookings.payment.store', $booking), [$key => $value])
            ->assertUnprocessable()->assertJsonValidationErrors($key);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(BookingStatus::Approved, $booking->fresh()->status);
    }

    #[TestWith([0])]
    #[TestWith([-1])]
    public function test_deadline_at_or_before_now_rejects_payment(int $minutes): void
    {
        $booking = $this->booking(['payment_due_at' => now()->addMinutes($minutes)]);
        $this->mock(PaymentService::class)->shouldNotReceive('createCheckout');
        $this->actingAs($booking->customer)->postJson(route('bookings.payment.store', $booking))->assertConflict();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_missing_price_snapshot_rejects_payment(): void
    {
        $booking = $this->booking();
        $booking->priceSnapshot->lines()->delete();
        $booking->priceSnapshot->delete();
        $this->mock(PaymentService::class)->shouldNotReceive('createCheckout');
        $this->actingAs($booking->customer)->postJson(route('bookings.payment.store', $booking))->assertConflict();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_customer_without_payment_capability_cannot_initiate(): void
    {
        $booking = $this->booking();
        $booking->customer->removeRole('customer');
        $this->mock(PaymentService::class)->shouldNotReceive('createCheckout');
        $this->actingAs($booking->customer)->postJson(route('bookings.payment.store', $booking))->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_inconsistent_price_snapshot_cannot_start_checkout(): void
    {
        $booking = $this->booking();
        $booking->priceSnapshot->lines()->sole()->update(['amount_minor' => 4999]);
        $this->mock(PaymentService::class)->shouldNotReceive('createCheckout');
        $this->actingAs($booking->customer)->postJson(route('bookings.payment.store', $booking))->assertConflict();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_uncertain_provider_failure_retains_pending_payment_and_retry_reuses_identity(): void
    {
        $booking = $this->booking();
        $service = $this->mock(PaymentService::class);
        $service->shouldReceive('createCheckout')->once()->andThrow(new \RuntimeException('Network timeout'));
        $this->actingAs($booking->customer)->postJson(route('bookings.payment.store', $booking))->assertConflict();
        $payment = Payment::query()->sole();
        $this->assertSame('pending', $payment->status->value);
        $this->assertNull($payment->provider_session_id);
        $this->assertSame(BookingStatus::Approved, $booking->fresh()->status);
        $parameters = $payment->checkout_parameters;
        $expiry = $payment->session_expires_at->toDateTimeString();
        $this->assertSame(5000, $parameters['line_items'][0]['price_data']['unit_amount']);
        $this->assertSame('gbp', $parameters['line_items'][0]['price_data']['currency']);
        $this->assertSame($payment->reference, $parameters['metadata']['payment_reference']);
        $this->travelTo(now()->addMinutes(5));

        $this->mock(PaymentService::class)->shouldReceive('createCheckout')->once()
            ->withArgs(fn (Payment $retry): bool => $retry->id === $payment->id && $retry->reference === $payment->reference
                && $retry->checkout_parameters === $parameters && $retry->session_expires_at->toDateTimeString() === $expiry
                && $retry->amount_minor === 5000 && $retry->currency === 'GBP')
            ->andReturn($this->checkout());
        $this->postJson(route('bookings.payment.store', $booking))->assertSuccessful();
        $this->assertDatabaseCount('payments', 1);
    }

    #[TestWith(['failed'])]
    #[TestWith(['expired'])]
    public function test_terminal_attempt_allows_distinct_payment_retry(string $status): void
    {
        $booking = $this->booking();
        $previous = Payment::factory()->for($booking)->create(['status' => $status]);
        $this->mock(PaymentService::class)->shouldReceive('createCheckout')->once()
            ->withArgs(fn (Payment $payment): bool => $payment->reference !== $previous->reference)
            ->andReturn($this->checkout());
        $this->actingAs($booking->customer)->postJson(route('bookings.payment.store', $booking))->assertSuccessful();
        $this->assertDatabaseCount('payments', 2);
        $this->assertSame($status, $previous->fresh()->status->value);
    }

    public function test_expired_local_pending_attempt_cannot_create_another_charge(): void
    {
        $booking = $this->booking();
        Payment::factory()->for($booking)->create(['session_expires_at' => now()->subSecond()]);
        $this->mock(PaymentService::class)->shouldNotReceive('createCheckout');
        $this->actingAs($booking->customer)->postJson(route('bookings.payment.store', $booking))->assertConflict();
        $this->assertDatabaseCount('payments', 1);
    }

    #[TestWith(['deadline'])]
    #[TestWith(['start'])]
    public function test_missing_deadline_or_started_booking_cannot_initiate(string $invalid): void
    {
        $booking = $this->booking($invalid === 'deadline'
            ? ['payment_due_at' => null]
            : ['starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
        $this->mock(PaymentService::class)->shouldNotReceive('createCheckout');
        $this->actingAs($booking->customer)->postJson(route('bookings.payment.store', $booking))->assertConflict();
        $this->assertDatabaseCount('payments', 0);
    }

    #[TestWith([1799, false])]
    #[TestWith([1800, true])]
    public function test_new_checkout_enforces_stripe_minimum_session_duration(int $seconds, bool $eligible): void
    {
        $booking = $this->booking(['payment_due_at' => now()->addSeconds($seconds)]);
        $mock = $this->mock(PaymentService::class);
        if ($eligible) {
            $mock->shouldReceive('createCheckout')->once()->andReturn($this->checkout());
        } else {
            $mock->shouldNotReceive('createCheckout');
        }
        $response = $this->actingAs($booking->customer)->postJson(route('bookings.payment.store', $booking));
        if ($eligible) {
            $response->assertSuccessful();
            $this->assertSame($booking->payment_due_at->timestamp, Payment::query()->sole()->session_expires_at->timestamp);
        } else {
            $response->assertConflict();
            $this->assertDatabaseCount('payments', 0);
        }
    }

    public function test_missing_permanent_protection_prevents_payment(): void
    {
        $booking = $this->booking();
        $booking->allocationOccupancy()->sole()->allocationUnits()->detach();
        $booking->allocationOccupancy()->delete();
        $this->mock(PaymentService::class)->shouldNotReceive('createCheckout');
        $this->actingAs($booking->customer)->postJson(route('bookings.payment.store', $booking))->assertConflict();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_unsupported_snapshot_currency_prevents_checkout(): void
    {
        $booking = $this->booking();
        $booking->priceSnapshot->update(['currency' => 'USD']);
        $this->mock(PaymentService::class)->shouldNotReceive('createCheckout');
        $this->actingAs($booking->customer)->postJson(route('bookings.payment.store', $booking))->assertConflict();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_audited_snapshot_override_controls_server_payment_amount(): void
    {
        $booking = $this->booking();
        $manager = User::factory()->create();
        $booking->priceSnapshot->update([
            'final_total_minor' => 4500, 'override_original_total_minor' => 5000,
            'override_adjusted_total_minor' => 4500, 'override_reason' => 'Agreed customer adjustment',
            'override_responsible_user_id' => $manager->id, 'override_responsible_user_name' => $manager->name,
            'override_adjusted_at' => now(),
        ]);
        $this->mock(PaymentService::class)->shouldReceive('createCheckout')->once()
            ->withArgs(fn (Payment $payment): bool => $payment->amount_minor === 4500)->andReturn($this->checkout());
        $this->actingAs($booking->customer)->postJson(route('bookings.payment.store', $booking))->assertSuccessful();
        $this->assertDatabaseHas('payments', ['booking_id' => $booking->id, 'amount_minor' => 4500]);
    }

    public function test_customer_booking_index_contains_only_owned_records(): void
    {
        $owned = $this->booking();
        $other = $this->booking();
        $this->withoutVite()->actingAs($owned->customer)->get(route('bookings.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('bookings/Index')
            ->has('bookings', 1)->where('bookings.0.id', $owned->id));
        $this->assertNotSame($owned->customer_id, $other->customer_id);
    }

    public function test_customer_without_view_capability_cannot_open_index_or_payment_status(): void
    {
        $booking = $this->booking();
        $booking->customer->removeRole('customer');
        $this->actingAs($booking->customer)->get(route('bookings.index'))->assertForbidden();
        $this->get(route('bookings.payment.show', $booking))->assertForbidden();
    }

    public function test_unverified_customer_cannot_open_booking_index_or_initiate_payment(): void
    {
        $booking = $this->booking();
        $booking->customer->forceFill(['email_verified_at' => null])->save();
        $this->mock(PaymentService::class)->shouldNotReceive('createCheckout');
        $this->actingAs($booking->customer)->get(route('bookings.index'))->assertRedirect(route('verification.notice'));
        $this->get(route('bookings.payment.show', $booking))->assertRedirect(route('verification.notice'));
        $this->postJson(route('bookings.payment.store', $booking))->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_repeated_initiation_reuses_existing_checkout_without_second_provider_call(): void
    {
        $booking = $this->booking();
        $this->mock(PaymentService::class)->shouldReceive('createCheckout')->once()->andReturn($this->checkout());
        $this->actingAs($booking->customer);
        $this->postJson(route('bookings.payment.store', $booking))->assertSuccessful();
        $this->postJson(route('bookings.payment.store', $booking))->assertSuccessful();
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_success_redirect_is_read_only_and_does_not_confirm_booking(): void
    {
        $booking = $this->booking();
        $this->withoutVite()->actingAs($booking->customer)
            ->get(route('bookings.payment.show', $booking).'?success=1&session_id=cs_forged&payment_status=paid')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('bookings/Payment'));
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(BookingStatus::Approved, $booking->fresh()->status);
        $this->assertSame(FinancialStatus::AwaitingPayment, $booking->fresh()->financial_status);
    }

    public function test_payment_return_shows_authoritative_pending_state_without_provider_details(): void
    {
        $booking = $this->booking();
        $this->mock(PaymentService::class)->shouldReceive('createCheckout')->once()->andReturn($this->checkout());
        $this->actingAs($booking->customer)->postJson(route('bookings.payment.store', $booking))->assertSuccessful();
        $this->withoutVite()->get(route('bookings.payment.show', $booking).'?success=1')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('bookings/Payment')
            ->where('booking.status', 'approved')->where('booking.financial_status', 'awaiting_payment')
            ->where('payment.status', 'pending')->where('payment.requires_review', false)
            ->missing('payment.provider_session_id')->missing('payment.provider_payment_intent_id')
            ->missing('payment.checkout_parameters')->missing('payment.reconciliation_issue'));
        $this->assertSame(BookingStatus::Approved, $booking->fresh()->status);
        $this->assertSame('pending', Payment::query()->sole()->status->value);
    }

    /** @param array<string, mixed> $attributes */
    private function booking(array $attributes = []): Booking
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'starts_at' => now()->addDays(4), 'ends_at' => now()->addDays(4)->addHours(2),
            'status' => BookingStatus::Approved, 'financial_status' => FinancialStatus::AwaitingPayment,
            'payment_due_at' => now()->addDay(),
            ...$attributes,
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

        return $booking;
    }

    /** @return array{id: string, url: string, payment_intent: string, livemode: bool} */
    private function checkout(): array
    {
        return ['id' => 'cs_test', 'url' => 'https://checkout.stripe.com/c/pay_test', 'payment_intent' => 'pi_test', 'livemode' => false];
    }
}
