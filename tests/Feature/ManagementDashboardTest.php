<?php

namespace Tests\Feature;

use App\Enums\BillingMethod;
use App\Enums\BookingStatus;
use App\Enums\ClosureImpactStatus;
use App\Enums\FinancialStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OperationalIssueStatus;
use App\Filament\Resources\AvailabilityBlocks\AvailabilityBlockResource;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Filament\Resources\Incidents\IncidentResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Widgets\Management\OperationalOverview;
use App\Models\AvailabilityBlock;
use App\Models\AvailabilityBlockBookingImpact;
use App\Models\Booking;
use App\Models\BookingPriceSnapshot;
use App\Models\Centre;
use App\Models\DamageReport;
use App\Models\Facility;
use App\Models\Incident;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Organisation;
use App\Models\Resource;
use App\Models\User;
use App\Services\ManagementDashboardQuery;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ManagementDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemRoleSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('management'));
        Carbon::setTestNow('2026-10-07 12:00:00 Europe/London');
        CarbonImmutable::setTestNow('2026-10-07 12:00:00 Europe/London');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_management_dashboard_shows_actionable_records_only_for_assigned_centres(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        [$riverside, $riversideResource] = $this->venue('Riverside');
        [$hillside, $hillsideResource] = $this->venue('Hillside');
        $manager->assignedCentres()->attach($riverside);
        $referenceSuffix = Str::lower(Str::random(8));
        $pendingReference = 'BKG-RIVER-PENDING-'.$referenceSuffix;

        $organisation = Organisation::factory()->create(['name' => 'Riverside Community Group']);
        $pending = $this->booking($riversideResource, [
            'reference' => $pendingReference,
            'organisation_id' => $organisation->id,
            'status' => BookingStatus::Requested,
            'financial_status' => FinancialStatus::NotDue,
        ]);
        $this->booking($hillsideResource, [
            'reference' => 'BKG-HILL-PENDING-'.$referenceSuffix,
            'status' => BookingStatus::Requested,
            'financial_status' => FinancialStatus::NotDue,
        ]);

        $awaitingPayment = $this->booking($riversideResource, [
            'reference' => 'BKG-RIVER-PAY-'.$referenceSuffix,
            'status' => BookingStatus::Approved,
            'financial_status' => FinancialStatus::AwaitingPayment,
            'billing_method' => BillingMethod::Card,
            'payment_due_at' => CarbonImmutable::now()->addDay(),
        ]);
        BookingPriceSnapshot::factory()->for($awaitingPayment)->create();
        $this->booking($riversideResource, [
            'reference' => 'BKG-RIVER-NOT-DUE-'.$referenceSuffix,
            'status' => BookingStatus::Approved,
            'financial_status' => FinancialStatus::NotDue,
        ]);
        $this->booking($riversideResource, [
            'reference' => 'BKG-RIVER-PAY-EXPIRED-'.$referenceSuffix,
            'status' => BookingStatus::Approved,
            'financial_status' => FinancialStatus::AwaitingPayment,
            'billing_method' => BillingMethod::Card,
            'payment_due_at' => CarbonImmutable::now()->subMinute(),
        ]);

        $todayBooking = $this->booking($riversideResource, [
            'reference' => 'BKG-RIVER-TODAY-'.$referenceSuffix,
            'status' => BookingStatus::Confirmed,
            'financial_status' => FinancialStatus::Paid,
            'starts_at' => CarbonImmutable::parse('2026-10-07 15:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-07 16:00:00', 'UTC'),
        ]);

        $invoicedBooking = $this->booking($riversideResource, [
            'reference' => 'BKG-RIVER-INVOICE-'.$referenceSuffix,
            'status' => BookingStatus::Confirmed,
            'financial_status' => FinancialStatus::Invoiced,
        ]);
        $overdueInvoice = $this->invoiceFor($manager, $invoicedBooking, 'INV-RIVER-OVERDUE-'.$referenceSuffix, CarbonImmutable::today()->subDay());
        $dueTodayBooking = $this->booking($riversideResource, [
            'status' => BookingStatus::Confirmed,
            'financial_status' => FinancialStatus::Invoiced,
        ]);
        $this->invoiceFor($manager, $dueTodayBooking, 'INV-RIVER-DUE-TODAY-'.$referenceSuffix, CarbonImmutable::today());
        $paidBooking = $this->booking($riversideResource, [
            'status' => BookingStatus::Confirmed,
            'financial_status' => FinancialStatus::Paid,
        ]);
        $paidInvoice = $this->invoiceFor($manager, $paidBooking, 'INV-RIVER-PAID-'.$referenceSuffix, CarbonImmutable::today()->subDay(), InvoiceStatus::Paid);
        $hillsideBooking = $this->booking($hillsideResource, [
            'reference' => 'BKG-HILL-INVOICE-'.$referenceSuffix,
            'status' => BookingStatus::Confirmed,
        ]);
        $this->invoiceFor($manager, $hillsideBooking, 'INV-HILL-OVERDUE-'.$referenceSuffix, CarbonImmutable::today()->subDay());

        $damage = DamageReport::factory()->create([
            'booking_id' => $todayBooking->id,
            'status' => OperationalIssueStatus::Open,
            'description' => 'A damaged court divider needs follow-up.',
        ]);
        $resolvedDamage = DamageReport::factory()->create([
            'booking_id' => $todayBooking->id,
            'status' => OperationalIssueStatus::Resolved,
        ]);

        $incident = Incident::factory()->create([
            'booking_id' => $todayBooking->id,
            'status' => OperationalIssueStatus::Reviewed,
            'title' => 'First aid follow-up',
        ]);
        Incident::factory()->create([
            'booking_id' => $todayBooking->id,
            'status' => OperationalIssueStatus::Closed,
        ]);

        $block = AvailabilityBlock::factory()->forCentre($riverside)->create(['reason' => 'Lighting maintenance']);
        $impact = AvailabilityBlockBookingImpact::factory()->create([
            'availability_block_id' => $block->id,
            'booking_id' => $todayBooking->id,
            'status' => ClosureImpactStatus::Unresolved,
        ]);
        AvailabilityBlockBookingImpact::factory()->create([
            'availability_block_id' => $block->id,
            'booking_id' => $awaitingPayment->id,
            'status' => ClosureImpactStatus::Resolved,
            'resolved_at' => CarbonImmutable::now(),
            'resolved_by' => $manager->id,
        ]);

        $this->actingAs($manager);

        $this->get('/management')->assertOk();
        $widget = Livewire::test(OperationalOverview::class)
            ->assertSee('Pending requests')
            ->assertSee('Awaiting payment')
            ->assertSee('Overdue invoices')
            ->assertSee('Today’s bookings')
            ->assertSee('Operational issues')
            ->assertSee('Closure impacts')
            ->assertSee('Open incidents')
            ->assertSee($pendingReference)
            ->assertSee('GBP 50.00 due')
            ->assertSee('Riverside Community Group')
            ->assertDontSee('BKG-HILL-PENDING-'.$referenceSuffix)
            ->assertDontSee('BKG-RIVER-NOT-DUE-'.$referenceSuffix)
            ->assertDontSee('BKG-RIVER-PAY-EXPIRED-'.$referenceSuffix)
            ->assertSee('INV-RIVER-OVERDUE-'.$referenceSuffix)
            ->assertDontSee('INV-RIVER-DUE-TODAY-'.$referenceSuffix)
            ->assertDontSee('INV-RIVER-PAID-'.$referenceSuffix)
            ->assertDontSee('INV-HILL-OVERDUE-'.$referenceSuffix)
            ->assertSee('BKG-RIVER-TODAY-'.$referenceSuffix)
            ->assertSee('16:00–17:00')
            ->assertDontSee('in UTC')
            ->assertSee('First aid follow-up')
            ->assertSee('Lighting maintenance')
            ->assertSee('Damage report')
            ->assertDontSee('A damaged court divider needs follow-up.');

        $widget->assertSee(BookingResource::getUrl('view', ['record' => $pending], panel: 'management'), false)
            ->assertSee(BookingResource::getUrl('view', ['record' => $todayBooking], panel: 'management'), false)
            ->assertSee(InvoiceResource::getUrl('view', ['record' => $overdueInvoice], panel: 'management'), false)
            ->assertSee(DamageReportResource::getUrl('view', ['record' => $damage], panel: 'management'), false)
            ->assertSee(IncidentResource::getUrl('view', ['record' => $incident], panel: 'management'), false)
            ->assertSee(AvailabilityBlockResource::getUrl('view', ['record' => $block], panel: 'management'), false);

        $this->get(BookingResource::getUrl('view', ['record' => $pending], panel: 'management'))->assertOk();
        $this->get(BookingResource::getUrl('view', ['record' => $todayBooking], panel: 'management'))->assertOk();
        $this->get(InvoiceResource::getUrl('view', ['record' => $overdueInvoice], panel: 'management'))->assertOk();
        $this->get(DamageReportResource::getUrl('view', ['record' => $damage], panel: 'management'))->assertOk();
        $this->get(IncidentResource::getUrl('view', ['record' => $incident], panel: 'management'))->assertOk();
        $this->get(AvailabilityBlockResource::getUrl('view', ['record' => $block], panel: 'management'))->assertOk();

        $this->assertSame(1, $this->dashboardSectionCount($manager, 'pendingBookings'));
        $this->assertSame(1, $this->dashboardSectionCount($manager, 'awaitingPayment'));
        $this->assertSame(1, $this->dashboardSectionCount($manager, 'overdueInvoices'));
        $this->assertSame(1, $this->dashboardSectionCount($manager, 'todaysBookings'));
        $this->assertSame(1, $this->dashboardSectionCount($manager, 'operationalIssues'));
        $this->assertSame(1, $this->dashboardSectionCount($manager, 'closureImpacts'));
        $this->assertSame(1, $this->dashboardSectionCount($manager, 'openIncidents'));
        $this->assertSame(InvoiceStatus::Paid, $paidInvoice->fresh()->status);
        $this->assertTrue($overdueInvoice->isOverdue());
        $this->assertFalse(Invoice::query()->where('reference', 'INV-RIVER-DUE-TODAY-'.$referenceSuffix)->firstOrFail()->isOverdue());
        $this->assertSame(OperationalIssueStatus::Open, $damage->fresh()->status);
        $this->assertSame(OperationalIssueStatus::Resolved, $resolvedDamage->fresh()->status);
        $this->assertSame(OperationalIssueStatus::Reviewed, $incident->fresh()->status);
        $this->assertSame(ClosureImpactStatus::Unresolved, $impact->fresh()->status);
    }

    public function test_non_managers_cannot_access_management_dashboard(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $this->actingAs($customer)->get('/management')->assertForbidden();
    }

    public function test_dashboard_hides_incident_and_damage_data_when_the_manager_lacks_that_permission(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        [$centre, $resource] = $this->venue('Riverside');
        $manager->assignedCentres()->attach($centre);
        $booking = $this->booking($resource, ['status' => BookingStatus::Confirmed]);
        $incident = Incident::factory()->create(['booking_id' => $booking->id, 'title' => 'Restricted incident']);
        $damage = DamageReport::factory()->create(['booking_id' => $booking->id, 'description' => 'Restricted damage report']);

        $manager->roles()->firstOrFail()->revokePermissionTo('incidents.manage');
        $this->actingAs($manager);

        $widget = Livewire::test(OperationalOverview::class)
            ->assertDontSee('Restricted incident')
            ->assertDontSee('Restricted damage report');

        $this->assertSame(0, $this->dashboardSectionCount($manager, 'operationalIssues'));
        $this->assertSame(0, $this->dashboardSectionCount($manager, 'openIncidents'));
        $this->assertSame(OperationalIssueStatus::Open, $incident->fresh()->status);
        $this->assertSame(OperationalIssueStatus::Open, $damage->fresh()->status);
    }

    public function test_dashboard_uses_clear_empty_states_when_there_is_no_actionable_work(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->assignedCentres()->attach(Centre::factory()->create());

        $this->actingAs($manager)->get('/management')->assertOk();
        Livewire::test(OperationalOverview::class)
            ->assertSee('No pending booking requests.')
            ->assertSee('No approved bookings are awaiting payment.')
            ->assertSee('No overdue invoices.')
            ->assertSee('No confirmed bookings are scheduled to start today.')
            ->assertSee('No unresolved operational issues.')
            ->assertSee('No unresolved closure impacts.')
            ->assertSee('No open incidents.');
    }

    public function test_todays_bookings_use_the_uk_calendar_day_during_british_summer_time(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        [$centre, $resource] = $this->venue('Riverside');
        $manager->assignedCentres()->attach($centre);
        $referenceSuffix = Str::lower(Str::random(8));

        $this->booking($resource, [
            'reference' => 'BKG-UK-DAY-START-'.$referenceSuffix,
            'status' => BookingStatus::Confirmed,
            'starts_at' => CarbonImmutable::parse('2026-10-06 23:30:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-07 00:30:00', 'UTC'),
        ]);
        $this->booking($resource, [
            'reference' => 'BKG-NEXT-UK-DAY-'.$referenceSuffix,
            'status' => BookingStatus::Confirmed,
            'starts_at' => CarbonImmutable::parse('2026-10-07 23:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-08 00:00:00', 'UTC'),
        ]);

        $this->actingAs($manager);

        Livewire::test(OperationalOverview::class)
            ->assertSee('BKG-UK-DAY-START-'.$referenceSuffix)
            ->assertDontSee('BKG-NEXT-UK-DAY-'.$referenceSuffix);

        $this->assertSame(1, $this->dashboardSectionCount($manager, 'todaysBookings'));
    }

    public function test_dashboard_discloses_when_pending_requests_are_capped_at_five(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        [$centre, $resource] = $this->venue('Riverside');
        $manager->assignedCentres()->attach($centre);
        $referenceSuffix = Str::lower(Str::random(8));

        for ($requestNumber = 1; $requestNumber <= 6; $requestNumber++) {
            $this->booking($resource, [
                'reference' => 'BKG-PREVIEW-'.$referenceSuffix.'-'.$requestNumber,
                'status' => BookingStatus::Requested,
            ]);
        }

        $this->actingAs($manager);

        Livewire::test(OperationalOverview::class)
            ->assertSee('Showing 5 of 6 requests.')
            ->assertSee('Review all requests');

        $pendingBookings = app(ManagementDashboardQuery::class)->forManager($manager)['pendingBookings'];

        $this->assertSame(6, $pendingBookings['count']);
        $this->assertCount(5, $pendingBookings['records']);
    }

    /** @return array{Centre, resource} */
    private function venue(string $name): array
    {
        $centre = Centre::factory()->create(['name' => $name]);
        $facility = Facility::factory()->create(['centre_id' => $centre->id]);
        $resource = Resource::factory()->create(['facility_id' => $facility->id]);

        return [$centre, $resource];
    }

    /** @param array<string, mixed> $attributes */
    private function booking(Resource $resource, array $attributes = []): Booking
    {
        return Booking::factory()->for($resource)->create($attributes);
    }

    private function invoiceFor(User $manager, Booking $booking, string $reference, CarbonImmutable $dueDate, InvoiceStatus $status = InvoiceStatus::Issued): Invoice
    {
        $invoice = Invoice::factory()->create([
            'reference' => $reference,
            'customer_id' => $booking->customer_id,
            'organisation_id' => $booking->organisation_id,
            'issued_by' => $manager->id,
            'issue_date' => $dueDate->subDays(30)->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'status' => $status,
            'paid_at' => $status === InvoiceStatus::Paid ? CarbonImmutable::now() : null,
        ]);

        InvoiceLine::factory()->for($invoice)->for($booking)->create();

        return $invoice;
    }

    private function dashboardSectionCount(User $manager, string $section): int
    {
        return app(ManagementDashboardQuery::class)->forManager($manager)[$section]['count'];
    }
}
