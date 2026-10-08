<?php

namespace Tests\Feature\Filament;

use App\Actions\IssueInvoice;
use App\Actions\RecordManualInvoicePayment;
use App\Enums\BillingMethod;
use App\Enums\BookingStatus;
use App\Enums\FinancialStatus;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Booking;
use App\Models\BookingPriceLine;
use App\Models\BookingPriceSnapshot;
use App\Models\CustomerInvoiceTerms;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemRoleSeeder::class);
        $this->freezeTime();
    }

    public function test_manager_can_grant_and_revoke_centre_invoice_terms_through_booking_review(): void
    {
        $booking = Booking::factory()->create();
        $manager = $this->manager($booking);
        $this->actingAs($manager);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('invoiceTerms', data: ['enabled' => true, 'term_days' => 14])
            ->assertHasNoActionErrors();

        $terms = CustomerInvoiceTerms::query()->sole();
        $this->assertTrue($terms->enabled);
        $this->assertSame(14, $terms->term_days);
        $this->assertSame($booking->customer_id, $terms->customer_id);
        $this->assertSame($booking->centre_id, $terms->centre_id);
        $this->assertSame($manager->id, $terms->authorised_by);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('invoiceTerms', data: ['enabled' => false, 'term_days' => 14])
            ->assertHasNoActionErrors();

        $this->assertFalse($terms->fresh()->enabled);
        $this->assertSame(BookingStatus::Requested, $booking->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_invoice_issue_and_manual_settlement_actions_use_authoritative_money(): void
    {
        $booking = $this->invoiceBooking();
        $manager = $this->manager($booking);
        $this->actingAs($manager);

        Livewire::test(ListInvoices::class)
            ->callAction('issueInvoice', data: ['booking_ids' => [$booking->id], 'total_minor' => 1])
            ->assertHasNoActionErrors();

        $invoice = Invoice::query()->sole();
        $this->assertSame(5000, $invoice->total_minor);
        $this->assertSame(FinancialStatus::Invoiced, $booking->fresh()->financial_status);

        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->callAction('recordPayment', data: ['external_reference' => 'EXT-UI-123', 'note' => 'Externally received and verified.', 'amount_minor' => 1])
            ->assertHasNoActionErrors();

        $payment = Payment::query()->sole();
        $this->assertSame(5000, $payment->amount_minor);
        $this->assertSame('manual', $payment->provider);
        $this->assertSame($manager->id, $payment->recorded_by);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame(FinancialStatus::Paid, $booking->fresh()->financial_status);
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_forged_foreign_centre_booking_selection_cannot_issue_invoice(): void
    {
        $local = $this->invoiceBooking();
        $foreign = $this->invoiceBooking();
        $this->actingAs($this->manager($local));

        Livewire::test(ListInvoices::class)
            ->callAction('issueInvoice', data: ['booking_ids' => [$foreign->id]])
            ->assertSuccessful();

        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame(FinancialStatus::InvoiceOutstanding, $foreign->fresh()->financial_status);
    }

    public function test_invoice_containing_any_foreign_centre_charge_is_hidden(): void
    {
        $local = $this->invoiceBooking();
        $foreign = $this->invoiceBooking(['customer_id' => $local->customer_id]);
        $manager = $this->manager($local);
        $visible = app(IssueInvoice::class)->handle($manager, [$local->id]);
        $localCharge = $this->invoiceBooking(['customer_id' => $local->customer_id, 'resource_id' => $local->resource_id]);
        $mixed = Invoice::factory()->create(['customer_id' => $local->customer_id, 'total_minor' => 10000]);
        InvoiceLine::factory()->for($mixed)->for($localCharge)->create();
        InvoiceLine::factory()->for($mixed)->for($foreign)->create();
        $this->actingAs($manager);

        $this->assertSame([$visible->id], InvoiceResource::getEloquentQuery()->pluck('id')->all());
        $this->get(InvoiceResource::getUrl('view', ['record' => $mixed]))->assertNotFound();
    }

    public function test_manager_without_recording_permission_cannot_invoke_manual_settlement(): void
    {
        $booking = $this->invoiceBooking();
        $manager = $this->manager($booking);
        $invoice = app(IssueInvoice::class)->handle($manager, [$booking->id]);
        Role::findByName('manager')->revokePermissionTo('payments.record');
        $this->actingAs($manager);

        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertActionHidden('recordPayment')
            ->call('mountAction', 'recordPayment')
            ->call('callMountedAction');

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(InvoiceStatus::Issued, $invoice->fresh()->status);
    }

    public function test_operations_payment_permission_cannot_settle_a_management_invoice(): void
    {
        $booking = $this->invoiceBooking();
        $invoice = app(IssueInvoice::class)->handle($this->manager($booking), [$booking->id]);
        $assistant = User::factory()->create();
        $assistant->assignRole('leisure-assistant');
        $assistant->assignedCentres()->attach($booking->centre_id);

        $this->assertTrue($assistant->can('payments.record'));
        $this->expectException(AuthorizationException::class);

        try {
            app(RecordManualInvoicePayment::class)->handle($assistant, $invoice, 'FORGED-SETTLEMENT', 'Attempted from operations');
        } finally {
            $this->assertDatabaseCount('payments', 0);
            $this->assertSame(InvoiceStatus::Issued, $invoice->fresh()->status);
        }
    }

    #[TestWith(['customer'])]
    #[TestWith(['leisure-assistant'])]
    public function test_non_management_roles_cannot_access_invoice_management(string $role): void
    {
        $booking = $this->invoiceBooking();
        $manager = $this->manager($booking);
        $invoice = app(IssueInvoice::class)->handle($manager, [$booking->id]);
        $actor = User::factory()->create();
        $actor->assignRole($role);
        $actor->assignedCentres()->attach($booking->centre_id);

        $this->actingAs($actor)->get(InvoiceResource::getUrl('view', ['record' => $invoice]))->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    /** @param array<string, mixed> $attributes */
    private function invoiceBooking(array $attributes = []): Booking
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Confirmed,
            'financial_status' => FinancialStatus::InvoiceOutstanding,
            'billing_method' => BillingMethod::Invoice,
            'invoice_term_days' => 30,
            ...$attributes,
        ]);
        $snapshot = BookingPriceSnapshot::factory()->for($booking)->create();
        BookingPriceLine::factory()->for($snapshot, 'snapshot')->create();

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
