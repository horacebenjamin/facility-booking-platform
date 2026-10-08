<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\OrganisationRole;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\Payment;
use App\Models\ResourceRate;
use App\Models\User;
use App\Services\Documents\DocumentBuilder;
use App\Services\Documents\StatementBuilder;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DocumentDownloadTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemRoleSeeder::class);
        $this->travelTo(now()->setDate(2026, 10, 8)->setTime(12, 0));
    }

    public function test_invoice_pdf_uses_persisted_values_after_live_rate_changes(): void
    {
        $customer = $this->customer();
        $booking = $this->booking($customer);
        $rate = ResourceRate::factory()->create(['resource_id' => $booking->resource_id, 'amount_minor' => 6250]);
        $invoice = $this->invoice($booking);
        $rate->update(['amount_minor' => 9000]);

        $data = app(DocumentBuilder::class)->invoice($invoice);
        $this->assertSame('£125.00', $data['total']);
        $this->assertSame('£125.00', $data['lines'][0]['amount']);

        $response = $this->actingAs($customer)->get(route('invoices.pdf', $invoice));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('invoice-', $response->headers->get('content-disposition'));
        $this->actingAs($this->customer())->get(route('invoices.pdf', $invoice))->assertNotFound();
    }

    public function test_receipt_requires_successful_payment_and_keeps_provider_data_private(): void
    {
        $customer = $this->customer();
        $booking = $this->booking($customer);
        $payment = Payment::factory()->create(['booking_id' => $booking->id, 'customer_id' => $customer->id, 'status' => PaymentStatus::Succeeded, 'succeeded_at' => '2026-06-01 11:00:00', 'amount_minor' => 12500]);
        $data = app(DocumentBuilder::class)->receipt($payment);
        $this->assertSame('£125.00', $data['amount']);
        $this->assertSame($payment->reference, $data['reference']);
        $this->assertArrayNotHasKey('provider_session_id', $data);
        $this->assertArrayNotHasKey('checkout_url', $data);

        $this->actingAs($customer)->get(route('receipts.pdf', $payment))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->customer())->get(route('receipts.pdf', $payment))->assertNotFound();
        $payment->update(['status' => PaymentStatus::Failed, 'succeeded_at' => null]);
        $this->actingAs($customer)->get(route('receipts.pdf', $payment))->assertNotFound();
    }

    public function test_confirmation_requires_confirmed_booking_and_formats_bst_and_gmt(): void
    {
        $customer = $this->customer();
        $summer = $this->booking($customer, null, '2026-07-15 13:00:00');
        $winter = $this->booking($customer, null, '2026-01-15 13:00:00');
        $builder = app(DocumentBuilder::class);
        $this->assertStringContainsString('14:00 BST', $builder->confirmation($summer)['starts']);
        $this->assertStringContainsString('13:00 GMT', $builder->confirmation($winter)['starts']);
        $this->actingAs($customer)->get(route('bookings.confirmation', $summer))->assertOk();
        $this->actingAs($this->customer())->get(route('bookings.confirmation', $summer))->assertNotFound();
        $summer->update(['status' => BookingStatus::Requested]);
        $this->actingAs($customer)->get(route('bookings.confirmation', $summer))->assertNotFound();
    }

    public function test_personal_statement_excludes_organisation_activity_and_uses_london_boundary(): void
    {
        $customer = $this->customer();
        $personalInvoice = $this->invoice($this->booking($customer), 12500, '2026-03-29');
        $organisation = Organisation::factory()->create();
        $this->invoice($this->booking($customer, $organisation), 9900, '2026-03-29');
        $this->invoicePayment($personalInvoice, '2026-03-29 23:30:00', 12500);

        $statement = app(StatementBuilder::class)->build($customer, '2026-03-30', '2026-03-30');
        $this->assertSame(['£125.00'], $statement['opening']);
        $this->assertSame(['£0.00'], $statement['closing']);
        $this->assertCount(1, $statement['rows']);
        $this->assertSame('£125.00', $statement['rows'][0]['credit']);
        $this->assertStringContainsString('BST', $statement['rows'][0]['date']);
        $this->actingAs($customer)->get(route('statements.personal', ['from' => '2026-03-30', 'to' => '2026-03-30']))->assertOk();
    }

    public function test_organisation_statement_uses_live_finance_membership_and_excludes_personal_activity(): void
    {
        $customer = $this->customer();
        $organisation = Organisation::factory()->create();
        $membership = OrganisationMembership::factory()->create(['organisation_id' => $organisation->id, 'user_id' => $customer->id, 'role' => OrganisationRole::Finance]);
        $organisationInvoice = $this->invoice($this->booking($customer, $organisation), 9900, '2026-01-15');
        $this->invoice($this->booking($customer), 12500, '2026-01-15');
        $statement = app(StatementBuilder::class)->build($organisation, '2026-01-01', '2026-01-31');
        $this->assertCount(1, $statement['rows']);
        $this->assertSame($organisationInvoice->reference, $statement['rows'][0]['reference']);

        $url = route('organisations.statement', ['organisation' => $organisation, 'from' => '2026-01-01', 'to' => '2026-01-31']);
        $this->actingAs($customer)->get($url)->assertOk();
        $membership->update(['role' => OrganisationRole::BookingManager]);
        $this->get($url)->assertForbidden();
        $membership->delete();
        $this->get($url)->assertForbidden();
        $this->actingAs($this->customer())->get($url)->assertForbidden();
    }

    public function test_organisation_invoice_and_receipt_follow_finance_roles_and_tenant_boundary(): void
    {
        $booker = $this->customer();
        $organisation = Organisation::factory()->create();
        $membership = OrganisationMembership::factory()->create(['organisation_id' => $organisation->id, 'user_id' => $booker->id, 'role' => OrganisationRole::Finance]);
        $invoice = $this->invoice($this->booking($booker, $organisation));
        $payment = $this->invoicePayment($invoice, '2026-06-02 12:00:00', 12500);
        $this->actingAs($booker)->get(route('invoices.pdf', $invoice))->assertOk();
        $this->get(route('receipts.pdf', $payment))->assertOk();
        $membership->update(['role' => OrganisationRole::Member]);
        $this->get(route('invoices.pdf', $invoice))->assertNotFound();
        $this->get(route('receipts.pdf', $payment))->assertNotFound();

        $other = $this->customer();
        OrganisationMembership::factory()->create(['organisation_id' => Organisation::factory()->create()->id, 'user_id' => $other->id, 'role' => OrganisationRole::Owner]);
        $this->actingAs($other)->get(route('invoices.pdf', $invoice))->assertNotFound();
    }

    public function test_statement_keeps_direct_card_payments_out_of_invoice_balance_and_checks_period(): void
    {
        $customer = $this->customer();
        $booking = $this->booking($customer);
        Payment::factory()->create(['booking_id' => $booking->id, 'customer_id' => $customer->id, 'status' => PaymentStatus::Succeeded, 'succeeded_at' => '2026-01-31 23:30:00']);
        Payment::factory()->create(['booking_id' => $booking->id, 'customer_id' => $customer->id, 'status' => PaymentStatus::Succeeded, 'succeeded_at' => '2026-02-01 00:30:00']);
        $statement = app(StatementBuilder::class)->build($customer, '2026-01-31', '2026-01-31');
        $this->assertSame([], $statement['rows']);
        $this->assertSame([], $statement['closing']);
        $this->assertCount(1, $statement['card_payments']);
        $this->assertStringContainsString('GMT', $statement['card_payments'][0]['date']);
        $this->actingAs($customer)->get(route('statements.personal', ['from' => '2026-01-31', 'to' => '2026-01-30']))->assertSessionHasErrors('from');
        $this->get(route('statements.personal', ['from' => '2024-01-01', 'to' => '2026-01-31']))->assertSessionHasErrors('from');
    }

    public function test_personal_statement_future_end_date_returns_a_specific_error_and_keeps_input(): void
    {
        $customer = $this->customer();
        $response = $this->from(route('invoices.index'))->actingAs($customer)->get(route('statements.personal', [
            'from' => '2026-10-01',
            'to' => '2026-10-10',
        ]));

        $response->assertRedirect(route('invoices.index'))
            ->assertSessionHasErrors(['to' => 'The statement end date cannot be in the future.'])
            ->assertSessionHas('_old_input.from', '2026-10-01')
            ->assertSessionHas('_old_input.to', '2026-10-10');
    }

    public function test_organisation_statement_future_end_date_returns_a_specific_error_and_keeps_input(): void
    {
        $customer = $this->customer();
        $organisation = Organisation::factory()->create();
        OrganisationMembership::factory()->create([
            'organisation_id' => $organisation->id,
            'user_id' => $customer->id,
            'role' => OrganisationRole::Finance,
        ]);
        $response = $this->from(route('organisations.show', $organisation))->actingAs($customer)->get(route('organisations.statement', [
            'organisation' => $organisation,
            'from' => '2026-10-01',
            'to' => '2026-10-10',
        ]));

        $response->assertRedirect(route('organisations.show', $organisation))
            ->assertSessionHasErrors(['to' => 'The statement end date cannot be in the future.'])
            ->assertSessionHas('_old_input.from', '2026-10-01')
            ->assertSessionHas('_old_input.to', '2026-10-10');
    }

    public function test_management_documents_enforce_permissions_and_centre_scope(): void
    {
        $customer = $this->customer();
        $booking = $this->booking($customer);
        $invoice = $this->invoice($booking);
        $payment = Payment::factory()->create(['booking_id' => $booking->id, 'customer_id' => $customer->id, 'status' => PaymentStatus::Succeeded, 'succeeded_at' => '2026-06-01 11:00:00']);
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $this->actingAs($manager);
        $this->get(route('management.documents.invoices', $invoice))->assertForbidden();
        $this->get(route('management.documents.bookings', $booking))->assertForbidden();
        $this->get(route('management.documents.receipts', $payment))->assertForbidden();
        $manager->assignedCentres()->attach($booking->centre_id);
        $this->get(route('management.documents.invoices', $invoice))->assertOk();
        $this->get(route('management.documents.bookings', $booking))->assertOk();
        $this->get(route('management.documents.receipts', $payment))->assertOk();
        $assistant = User::factory()->create();
        $assistant->assignRole('leisure-assistant');
        $assistant->assignedCentres()->attach($booking->centre_id);
        $this->actingAs($assistant)->get(route('management.documents.receipts', $payment))->assertForbidden();
    }

    private function customer(): User
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        return $customer;
    }

    private function booking(User $customer, ?Organisation $organisation = null, string $startsAt = '2026-06-01 12:00:00'): Booking
    {
        return Booking::factory()->create(['customer_id' => $customer->id, 'organisation_id' => $organisation?->id, 'status' => BookingStatus::Confirmed, 'starts_at' => $startsAt, 'ends_at' => date('Y-m-d H:i:s', strtotime($startsAt.' +2 hours'))]);
    }

    private function invoice(Booking $booking, int $amount = 12500, string $date = '2026-06-01'): Invoice
    {
        $invoice = Invoice::factory()->create(['customer_id' => $booking->customer_id, 'organisation_id' => $booking->organisation_id, 'issue_date' => $date, 'due_date' => '2026-12-01', 'total_minor' => $amount]);
        InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'booking_id' => $booking->id, 'amount_minor' => $amount]);

        return $invoice;
    }

    private function invoicePayment(Invoice $invoice, string $succeededAt, int $amount): Payment
    {
        return Payment::factory()->create(['booking_id' => null, 'invoice_id' => $invoice->id, 'customer_id' => $invoice->customer_id, 'provider' => 'manual', 'provider_session_id' => null, 'provider_payment_intent_id' => null, 'checkout_url' => null, 'recorded_by' => $invoice->issued_by, 'external_reference' => 'BANK-REF', 'recording_note' => 'Received by bank transfer', 'session_expires_at' => null, 'live_mode' => false, 'status' => PaymentStatus::Succeeded, 'succeeded_at' => $succeededAt, 'amount_minor' => $amount]);
    }
}
