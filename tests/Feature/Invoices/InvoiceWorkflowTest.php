<?php

namespace Tests\Feature\Invoices;

use App\Actions\ApproveBooking;
use App\Actions\IssueInvoice;
use App\Actions\RecordManualInvoicePayment;
use App\Actions\SetCustomerInvoiceTerms;
use App\Enums\BillingMethod;
use App\Enums\BookingStatus;
use App\Enums\FinancialStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InvoiceUnavailable;
use App\Models\AllocationOccupancy;
use App\Models\AllocationUnit;
use App\Models\Booking;
use App\Models\BookingPriceLine;
use App\Models\BookingPriceSnapshot;
use App\Models\BookingSeries;
use App\Models\FacilityBookableHour;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ResourceBookableHour;
use App\Models\User;
use App\Services\OperationalOccupancyCalculator;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class InvoiceWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemRoleSeeder::class);
        $this->freezeTime();
    }

    public function test_multiple_booking_invoice_derives_lines_total_and_due_date_from_persisted_obligations(): void
    {
        $first = $this->booking();
        $second = $this->booking(['customer_id' => $first->customer_id, 'resource_id' => $first->resource_id]);
        $manager = $this->manager($first);

        $invoice = app(IssueInvoice::class)->handle($manager, [$second->id, $first->id]);

        $this->assertSame($first->customer_id, $invoice->customer_id);
        $this->assertSame('GBP', $invoice->currency);
        $this->assertSame(10000, $invoice->total_minor);
        $this->assertCount(2, $invoice->lines);
        $this->assertSame(10000, $invoice->lines->sum('amount_minor'));
        $this->assertTrue($invoice->due_date->equalTo($invoice->issue_date->addDays(30)));
        $this->assertSame('issued', $invoice->status->value);
        $this->assertSame(BookingStatus::Confirmed, $first->fresh()->status);
        $this->assertSame(FinancialStatus::Invoiced, $first->fresh()->financial_status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_manual_payment_settles_invoice_without_stripe_context(): void
    {
        $booking = $this->booking();
        $manager = $this->manager($booking);
        $invoice = app(IssueInvoice::class)->handle($manager, [$booking->id]);

        $payment = app(RecordManualInvoicePayment::class)->handle($manager, $invoice, 'BANK-123', 'Received and verified externally.');

        $this->assertSame('manual', $payment->provider);
        $this->assertSame($invoice->id, $payment->invoice_id);
        $this->assertNull($payment->booking_id);
        $this->assertSame($manager->id, $payment->recorded_by);
        $this->assertSame(5000, $payment->amount_minor);
        $this->assertSame('GBP', $payment->currency);
        $this->assertSame(PaymentStatus::Succeeded, $payment->status);
        $this->assertNull($payment->provider_session_id);
        $this->assertNull($payment->provider_payment_intent_id);
        $this->assertNull($payment->session_expires_at);
        $this->assertSame('paid', $invoice->fresh()->status->value);
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertSame(FinancialStatus::Paid, $booking->fresh()->financial_status);
    }

    public function test_multiple_booking_settlement_marks_each_charge_paid_and_leaves_other_obligations_untouched(): void
    {
        $first = $this->booking();
        $second = $this->booking(['resource_id' => $first->resource_id, 'customer_id' => $first->customer_id]);
        $sibling = $this->booking(['resource_id' => $first->resource_id, 'customer_id' => $first->customer_id]);
        $series = BookingSeries::factory()->create([
            'customer_id' => $first->customer_id, 'centre_id' => $first->centre_id, 'facility_id' => $first->facility_id,
            'resource_id' => $first->resource_id, 'occurrence_count' => 3,
        ]);
        foreach ([$first, $second, $sibling] as $index => $booking) {
            $booking->update(['booking_series_id' => $series->id, 'occurrence_index' => $index + 1]);
        }
        $manager = $this->manager($first);
        $invoice = app(IssueInvoice::class)->handle($manager, [$first->id, $second->id]);
        $payment = app(RecordManualInvoicePayment::class)->handle($manager, $invoice, 'BANK-SERIES', 'Verified external receipt.');
        $this->assertSame(10000, $payment->amount_minor);
        $this->assertSame(FinancialStatus::Paid, $first->fresh()->financial_status);
        $this->assertSame(FinancialStatus::Paid, $second->fresh()->financial_status);
        $this->assertSame(FinancialStatus::InvoiceOutstanding, $sibling->fresh()->financial_status);
        $this->assertSame(BookingStatus::Confirmed, $sibling->fresh()->status);
        $this->assertSame($series->id, $sibling->fresh()->booking_series_id);
        $this->assertSame(3, $sibling->fresh()->occurrence_index);
    }

    public function test_mismatched_invoice_total_or_line_cannot_be_settled(): void
    {
        $booking = $this->booking();
        $manager = $this->manager($booking);
        $invoice = app(IssueInvoice::class)->handle($manager, [$booking->id]);
        $invoice->update(['total_minor' => 1]);
        $this->expectException(InvoiceUnavailable::class);
        app(RecordManualInvoicePayment::class)->handle($manager, $invoice, 'BANK-FORGED', 'Verified externally.');
    }

    public function test_manager_cannot_settle_invoice_outside_assigned_centre(): void
    {
        $booking = $this->booking();
        $invoice = app(IssueInvoice::class)->handle($this->manager($booking), [$booking->id]);
        $other = $this->booking();
        $this->expectException(AuthorizationException::class);
        app(RecordManualInvoicePayment::class)->handle($this->manager($other), $invoice, 'BANK-FORGED', 'Verified externally.');
    }

    public function test_duplicate_settlement_cannot_create_second_financial_effect(): void
    {
        $booking = $this->booking();
        $manager = $this->manager($booking);
        $invoice = app(IssueInvoice::class)->handle($manager, [$booking->id]);
        app(RecordManualInvoicePayment::class)->handle($manager, $invoice, 'BANK-123', 'Verified.');

        try {
            app(RecordManualInvoicePayment::class)->handle($manager, $invoice, 'BANK-456', 'Repeated.');
            $this->fail('A paid invoice cannot receive another settlement.');
        } catch (InvoiceUnavailable) {
            $this->assertDatabaseCount('payments', 1);
            $this->assertSame('paid', $invoice->fresh()->status->value);
        }
    }

    public function test_duplicate_invoice_issue_cannot_invoice_booking_twice(): void
    {
        $booking = $this->booking();
        $manager = $this->manager($booking);
        app(IssueInvoice::class)->handle($manager, [$booking->id]);

        try {
            app(IssueInvoice::class)->handle($manager, [$booking->id]);
            $this->fail('An invoiced booking cannot be invoiced again.');
        } catch (InvoiceUnavailable) {
            $this->assertDatabaseCount('invoices', 1);
            $this->assertDatabaseCount('invoice_lines', 1);
        }
    }

    public function test_different_customers_cannot_be_combined_even_by_assigned_manager(): void
    {
        $first = $this->booking();
        $second = $this->booking(['resource_id' => $first->resource_id]);
        $manager = $this->manager($first);
        $this->expectException(InvoiceUnavailable::class);
        app(IssueInvoice::class)->handle($manager, [$first->id, $second->id]);
    }

    public function test_ordinary_customer_cannot_authorise_invoice_terms(): void
    {
        $booking = $this->booking();
        $this->expectException(AuthorizationException::class);
        app(SetCustomerInvoiceTerms::class)->handle($booking->customer, $booking, true, 30);
    }

    public function test_manager_without_centre_assignment_cannot_issue_invoice(): void
    {
        $booking = $this->booking();
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $this->expectException(AuthorizationException::class);
        app(IssueInvoice::class)->handle($manager, [$booking->id]);
    }

    public function test_customer_cannot_record_manual_payment(): void
    {
        $booking = $this->booking();
        $invoice = app(IssueInvoice::class)->handle($this->manager($booking), [$booking->id]);
        $this->expectException(AuthorizationException::class);
        app(RecordManualInvoicePayment::class)->handle($booking->customer, $invoice, 'FORGED', 'Forged settlement.');
    }

    public function test_card_booking_cannot_be_invoiced_by_forging_booking_id(): void
    {
        $booking = $this->booking(['billing_method' => BillingMethod::Card, 'invoice_term_days' => null, 'status' => BookingStatus::Approved, 'financial_status' => FinancialStatus::AwaitingPayment]);
        $this->expectException(InvoiceUnavailable::class);
        app(IssueInvoice::class)->handle($this->manager($booking), [$booking->id]);
    }

    public function test_inconsistent_price_snapshot_cannot_issue_invoice(): void
    {
        $booking = $this->booking();
        $booking->priceSnapshot->lines()->firstOrFail()->update(['amount_minor' => 5001]);
        $this->expectException(InvoiceUnavailable::class);
        app(IssueInvoice::class)->handle($this->manager($booking), [$booking->id]);
    }

    public function test_unsupported_snapshot_currency_cannot_issue_invoice(): void
    {
        $booking = $this->booking();
        $booking->priceSnapshot->update(['currency' => 'USD']);
        $this->expectException(InvoiceUnavailable::class);
        app(IssueInvoice::class)->handle($this->manager($booking), [$booking->id]);
    }

    public function test_supported_but_different_currencies_cannot_be_composed_into_one_invoice(): void
    {
        config(['payments.currencies' => ['GBP', 'USD']]);
        $first = $this->booking();
        $second = $this->booking(['resource_id' => $first->resource_id, 'customer_id' => $first->customer_id]);
        $second->priceSnapshot->update(['currency' => 'USD']);
        $this->expectException(InvoiceUnavailable::class);
        app(IssueInvoice::class)->handle($this->manager($first), [$first->id, $second->id]);
    }

    public function test_audited_booking_price_override_is_authoritative_for_invoice_charge(): void
    {
        $booking = $this->booking();
        $manager = $this->manager($booking);
        $booking->priceSnapshot->update([
            'override_original_total_minor' => 5000, 'override_adjusted_total_minor' => 4500, 'final_total_minor' => 4500,
            'override_reason' => 'Agreed exceptional event price.', 'override_responsible_user_id' => $manager->id,
            'override_responsible_user_name' => $manager->name, 'override_adjusted_at' => now(),
        ]);
        $invoice = app(IssueInvoice::class)->handle($manager, [$booking->id]);
        $this->assertSame(4500, $invoice->total_minor);
        $this->assertSame(4500, $invoice->lines->sole()->amount_minor);
    }

    public function test_customer_invoice_endpoints_isolate_ownership_and_expose_honest_outstanding_state(): void
    {
        $booking = $this->booking();
        $invoice = app(IssueInvoice::class)->handle($this->manager($booking), [$booking->id]);
        $other = $this->booking();
        $otherInvoice = app(IssueInvoice::class)->handle($this->manager($other), [$other->id]);
        $this->withoutVite()->actingAs($booking->customer)
            ->get(route('invoices.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('invoices/Index')
                ->has('invoices', 1)->where('invoices.0.id', $invoice->id));
        $this->get(route('invoices.show', $invoice))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('invoices/Show')
                ->where('invoice.outstanding_minor', 5000)->where('invoice.status', 'issued')
                ->missing('invoice.customer_id')->missing('invoice.payments'));
        $this->get(route('invoices.show', $otherInvoice))->assertNotFound();
    }

    public function test_invoice_booking_payment_page_redirects_to_authoritative_invoice_and_never_checkout(): void
    {
        $booking = $this->booking();
        $this->actingAs($booking->customer)->get(route('bookings.payment.show', $booking))->assertRedirect(route('bookings.index'));
        $invoice = app(IssueInvoice::class)->handle($this->manager($booking), [$booking->id]);
        $this->get(route('bookings.payment.show', $booking))->assertRedirect(route('invoices.show', $invoice));
        $this->postJson(route('bookings.payment.store', $booking))->assertConflict();
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertSame(FinancialStatus::Invoiced, $booking->fresh()->financial_status);
    }

    #[TestWith(['billing_method', 'invoice'])]
    #[TestWith(['invoice_term_days', 365])]
    #[TestWith(['invoice_id', 1])]
    #[TestWith(['invoice_eligible', true])]
    public function test_browser_cannot_supply_invoice_authority_to_card_payment(string $field, mixed $value): void
    {
        $booking = $this->booking(['billing_method' => BillingMethod::Card, 'invoice_term_days' => null,
            'status' => BookingStatus::Approved, 'financial_status' => FinancialStatus::AwaitingPayment, 'payment_due_at' => now()->addDay()]);
        $this->actingAs($booking->customer)->postJson(route('bookings.payment.store', $booking), [$field => $value])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(BillingMethod::Card, $booking->fresh()->billing_method);
    }

    public function test_centre_assignment_without_financial_capability_never_authorises_invoice_actions(): void
    {
        $booking = $this->booking();
        $actor = User::factory()->create();
        $actor->assignedCentres()->attach($booking->centre_id);
        $this->assertFalse(Gate::forUser($actor)->allows('manageInvoiceTerms', $booking));
        $this->assertFalse(Gate::forUser($actor)->allows('create', Invoice::class));
        $invoice = app(IssueInvoice::class)->handle($this->manager($booking), [$booking->id]);
        $this->assertFalse(Gate::forUser($actor)->allows('recordPayment', $invoice));
    }

    public function test_pending_stripe_attempt_prevents_invoice_issue(): void
    {
        $booking = $this->booking();
        Payment::factory()->for($booking)->create(['customer_id' => $booking->customer_id]);
        $this->expectException(InvoiceUnavailable::class);
        app(IssueInvoice::class)->handle($this->manager($booking), [$booking->id]);
    }

    public function test_invoice_and_manual_payment_have_responsible_actor_and_single_consequential_audit(): void
    {
        $booking = $this->booking();
        $manager = $this->manager($booking);
        $invoice = app(IssueInvoice::class)->handle($manager, [$booking->id]);
        $payment = app(RecordManualInvoicePayment::class)->handle($manager, $invoice, 'VERIFIED-1', 'External receipt verified.');
        $issueAudit = Activity::query()->where('subject_type', Invoice::class)->where('subject_id', $invoice->id)->where('event', 'invoice.issued')->sole();
        $this->assertSame($manager->id, $issueAudit->causer_id);
        $paymentAudit = Activity::query()->where('subject_type', Payment::class)->where('subject_id', $payment->id)->sole();
        $this->assertSame($manager->id, $paymentAudit->causer_id);
    }

    public function test_authorised_invoice_terms_confirm_on_approval_without_marking_paid(): void
    {
        $booking = $this->approvalBooking();
        $manager = $this->manager($booking);
        app(SetCustomerInvoiceTerms::class)->handle($manager, $booking, true, 30);
        $approved = app(ApproveBooking::class)->handle($manager, $booking);
        $this->assertSame(BillingMethod::Invoice, $approved->billing_method);
        $this->assertSame(BookingStatus::Confirmed, $approved->status);
        $this->assertSame(FinancialStatus::InvoiceOutstanding, $approved->financial_status);
        $this->assertSame(30, $approved->invoice_term_days);
        $this->assertNull($approved->payment_due_at);
        $this->assertNull($approved->allocationOccupancy->expires_at);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_customer_without_authorised_terms_remains_standard_card_workflow(): void
    {
        $booking = $this->approvalBooking();
        $approved = app(ApproveBooking::class)->handle($this->manager($booking), $booking);
        $this->assertSame(BillingMethod::Card, $approved->billing_method);
        $this->assertSame(BookingStatus::Approved, $approved->status);
        $this->assertSame(FinancialStatus::AwaitingPayment, $approved->financial_status);
        $this->assertNotNull($approved->payment_due_at);
        $this->assertNull($approved->invoice_term_days);
    }

    public function test_revoking_customer_terms_after_approval_preserves_agreed_invoice_obligation(): void
    {
        $booking = $this->approvalBooking();
        $manager = $this->manager($booking);
        app(SetCustomerInvoiceTerms::class)->handle($manager, $booking, true, 30);
        $approved = app(ApproveBooking::class)->handle($manager, $booking);
        app(SetCustomerInvoiceTerms::class)->handle($manager, $approved, false, 14);
        $invoice = app(IssueInvoice::class)->handle($manager, [$approved->id]);
        $this->assertSame(BillingMethod::Invoice, $approved->fresh()->billing_method);
        $this->assertSame(30, $approved->fresh()->invoice_term_days);
        $this->assertTrue($invoice->due_date->equalTo($invoice->issue_date->addDays(30)));
    }

    public function test_terms_in_another_centre_do_not_authorise_invoice_approval(): void
    {
        $first = $this->approvalBooking();
        $other = $this->approvalBooking();
        $other->update(['customer_id' => $first->customer_id]);
        app(SetCustomerInvoiceTerms::class)->handle($this->manager($first), $first, true, 30);
        $approved = app(ApproveBooking::class)->handle($this->manager($other), $other);
        $this->assertSame(BillingMethod::Card, $approved->billing_method);
        $this->assertSame(BookingStatus::Approved, $approved->status);
    }

    public function test_invoice_line_preserves_full_legitimate_resource_description(): void
    {
        $booking = $this->booking();
        $name = str_repeat('Long resource name ', 13).'suffix';
        $booking->resource->update(['name' => $name]);
        $invoice = app(IssueInvoice::class)->handle($this->manager($booking), [$booking->id]);
        $this->assertStringContainsString($name, $invoice->lines->sole()->description);
        $this->assertGreaterThan(255, strlen($invoice->lines->sole()->description));
    }

    public function test_invoice_endpoints_require_authentication_verification_and_view_capability(): void
    {
        $booking = $this->booking();
        $invoice = app(IssueInvoice::class)->handle($this->manager($booking), [$booking->id]);
        $this->get(route('invoices.index'))->assertRedirect(route('login'));
        $this->get(route('invoices.show', $invoice))->assertRedirect(route('login'));
        $booking->customer->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($booking->customer)->get(route('invoices.index'))->assertRedirect(route('verification.notice'));
        $booking->customer->forceFill(['email_verified_at' => now()])->save();
        $booking->customer->syncRoles([]);
        $this->actingAs($booking->customer->fresh())->get(route('invoices.index'))->assertForbidden();
        $this->get(route('invoices.show', $invoice))->assertForbidden();
    }

    private function approvalBooking(): Booking
    {
        $booking = $this->booking([
            'starts_at' => now()->addDays(4)->setTime(18, 0), 'ends_at' => now()->addDays(4)->setTime(20, 0),
            'status' => BookingStatus::Requested, 'financial_status' => FinancialStatus::NotDue,
            'billing_method' => BillingMethod::Card, 'invoice_term_days' => null,
        ]);
        $booking->allocationOccupancy->update(['expires_at' => now()->addHour()]);
        FacilityBookableHour::factory()->for($booking->resource->facility)->create([
            'day_of_week' => $booking->starts_at->dayOfWeekIso, 'opens_at' => '00:00:00', 'closes_at' => '23:59:59',
        ]);
        ResourceBookableHour::factory()->for($booking->resource)->create([
            'day_of_week' => $booking->starts_at->dayOfWeekIso, 'opens_at' => '00:00:00', 'closes_at' => '23:59:59',
        ]);

        return $booking;
    }

    public function test_invoice_audit_failure_rolls_back_header_lines_and_booking_financial_state(): void
    {
        $booking = $this->booking();
        $manager = $this->manager($booking);
        $event = 'eloquent.created: '.Activity::class;
        Event::listen($event, function (Activity $activity): void {
            if ($activity->event === 'invoice.issued') {
                throw new RuntimeException('Simulated audit persistence failure.');
            }
        });
        try {
            app(IssueInvoice::class)->handle($manager, [$booking->id]);
            $this->fail('Invoice issue must propagate audit failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated audit persistence failure.', $exception->getMessage());
            $this->assertDatabaseCount('invoices', 0);
            $this->assertDatabaseCount('invoice_lines', 0);
            $this->assertSame(FinancialStatus::InvoiceOutstanding, $booking->fresh()->financial_status);
        } finally {
            Event::forget($event);
        }
    }

    public function test_settlement_audit_failure_rolls_back_payment_invoice_and_booking_state(): void
    {
        $booking = $this->booking();
        $manager = $this->manager($booking);
        $invoice = app(IssueInvoice::class)->handle($manager, [$booking->id]);
        $event = 'eloquent.created: '.Activity::class;
        Event::listen($event, function (Activity $activity): void {
            if ($activity->event === 'invoice.paid') {
                throw new RuntimeException('Simulated settlement audit failure.');
            }
        });
        try {
            app(RecordManualInvoicePayment::class)->handle($manager, $invoice, 'BANK-ROLLBACK', 'Verified externally.');
            $this->fail('Settlement must propagate audit failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated settlement audit failure.', $exception->getMessage());
            $this->assertDatabaseCount('payments', 0);
            $this->assertSame('issued', $invoice->fresh()->status->value);
            $this->assertNull($invoice->fresh()->paid_at);
            $this->assertSame(FinancialStatus::Invoiced, $booking->fresh()->financial_status);
        } finally {
            Event::forget($event);
        }
    }

    public function test_different_agreed_terms_cannot_be_combined(): void
    {
        $first = $this->booking();
        $second = $this->booking(['resource_id' => $first->resource_id, 'customer_id' => $first->customer_id, 'invoice_term_days' => 14]);
        $this->expectException(InvoiceUnavailable::class);
        app(IssueInvoice::class)->handle($this->manager($first), [$first->id, $second->id]);
    }

    public function test_manual_payment_requires_external_reference_and_note(): void
    {
        $booking = $this->booking();
        $manager = $this->manager($booking);
        $invoice = app(IssueInvoice::class)->handle($manager, [$booking->id]);
        $this->expectException(ValidationException::class);
        app(RecordManualInvoicePayment::class)->handle($manager, $invoice, ' ', ' ');
    }

    /** @param array<string, mixed> $attributes */
    private function booking(array $attributes = []): Booking
    {
        $booking = Booking::factory()->create([
            'starts_at' => now()->addDays(4), 'ends_at' => now()->addDays(4)->addHours(2),
            'status' => BookingStatus::Confirmed, 'financial_status' => FinancialStatus::InvoiceOutstanding,
            'billing_method' => BillingMethod::Invoice, 'invoice_term_days' => 30, 'payment_due_at' => null,
            ...$attributes,
        ]);
        $snapshot = BookingPriceSnapshot::factory()->for($booking)->create();
        BookingPriceLine::factory()->for($snapshot, 'snapshot')->create();
        $unit = $booking->resource->allocationUnits()->first();
        if ($unit === null) {
            $unit = AllocationUnit::factory()->for($booking->resource->facility)->create();
            $booking->resource->syncAllocationUnits($unit);
        }
        $period = app(OperationalOccupancyCalculator::class)->calculate($booking->resource, $booking->starts_at, $booking->ends_at);
        $this->assertNotNull($period);
        $occupancy = AllocationOccupancy::factory()->for($booking)->create(['starts_at' => $period->startsAt, 'ends_at' => $period->endsAt, 'expires_at' => null]);
        $occupancy->allocationUnits()->attach($unit);
        $booking->customer->assignRole('customer');

        return $booking;
    }

    private function manager(Booking $booking): User
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->assignedCentres()->attach($booking->centre_id);

        return $manager;
    }
}
