<?php

namespace Tests\Feature;

use App\Enums\AttendanceState;
use App\Enums\BookingStatus;
use App\Enums\DayOfWeek;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Filament\Exports\BookingExporter;
use App\Filament\Exports\InvoiceExporter;
use App\Filament\Exports\PaymentExporter;
use App\Filament\Pages\Reporting;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\BookingPriceSnapshot;
use App\Models\Centre;
use App\Models\Facility;
use App\Models\FacilityBookableHour;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Organisation;
use App\Models\Payment;
use App\Models\Resource;
use App\Models\ResourceBookableHour;
use App\Models\User;
use App\Services\Reporting\ManagementReportQuery;
use App\Services\Reporting\ReportFilters;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Bus\ChainedBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ManagementReportingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemRoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00:00', 'Europe/London'));
        Filament::setCurrentPanel(Filament::getPanel('management'));
    }

    public function test_reports_centralise_collected_revenue_statuses_finance_and_organisation_ownership(): void
    {
        [$manager, $riverside, $resource] = $this->managerVenue('Riverside');
        $customer = User::factory()->create(['name' => 'Personal customer']);
        $organisation = Organisation::factory()->create(['name' => 'Riverside Juniors']);
        $personalBooking = $this->booking($resource, ['customer_id' => $customer->id]);
        Payment::factory()->for($personalBooking)->create(['amount_minor' => 5000, 'status' => PaymentStatus::Succeeded, 'succeeded_at' => '2026-10-06 09:30:00']);
        $organisationBooking = $this->booking($resource, ['organisation_id' => $organisation->id]);
        $invoice = Invoice::factory()->create(['customer_id' => $organisationBooking->customer_id, 'organisation_id' => $organisation->id, 'status' => InvoiceStatus::Paid, 'total_minor' => 7000, 'paid_at' => '2026-10-06 10:00:00']);
        InvoiceLine::factory()->for($invoice)->for($organisationBooking)->create(['amount_minor' => 7000]);
        $this->booking($resource, ['status' => BookingStatus::Cancelled]);
        $this->booking($resource, ['status' => BookingStatus::Rejected]);
        $this->booking($resource, [
            'attendance_state' => AttendanceState::NoShow,
            'no_show_recorded_at' => '2026-10-06 11:00:00',
        ]);

        $report = app(ManagementReportQuery::class)->summary($manager, $this->filters($riverside));

        $this->assertSame(12000, $report['revenue']['totalMinor']);
        $this->assertSame(2, $report['revenue']['transactions']);
        $this->assertSame(['label' => 'Riverside', 'amountMinor' => 12000], $report['revenue']['byCentre']['centre:'.$riverside->id]);
        $this->assertSame(['customer:'.$customer->id => ['label' => 'Personal customer', 'amountMinor' => 5000]], $report['revenue']['byCustomer']);
        $this->assertSame(['organisation:'.$organisation->id => ['label' => 'Riverside Juniors', 'amountMinor' => 7000]], $report['revenue']['byOrganisation']);
        $this->assertSame(3, $report['bookings']['confirmed']);
        $this->assertSame(1, $report['bookings']['cancelled']);
        $this->assertSame(1, $report['bookings']['rejected']);
        $this->assertSame(1, $report['bookings']['noShow']);
        $this->assertSame(1, $report['finance']['paidCount']);
        $this->assertSame(7000, $report['finance']['paidMinor']);
    }

    public function test_revenue_groups_by_facility_resource_and_settlement_period_without_merging_shared_names(): void
    {
        [$manager, $riverside, $courtA] = $this->managerVenue('Riverside');
        $annex = Facility::factory()->create(['centre_id' => $riverside->id, 'name' => 'Annex']);
        $courtA->update(['name' => 'Court 1']);
        $courtB = Resource::factory()->create(['facility_id' => $annex->id, 'name' => 'Court 1']);
        $this->settledPayment($this->booking($courtA), 3000, '2026-10-02 09:00:00');
        $this->settledPayment($this->booking($courtB), 2000, '2026-10-05 09:00:00');
        $advanceBooking = $this->booking($courtB, ['starts_at' => '2026-12-01 10:00:00', 'ends_at' => '2026-12-01 11:00:00']);
        $this->settledPayment($advanceBooking, 1500, '2026-10-05 10:00:00');
        $this->settledPayment($this->booking($courtA), 9900, '2026-09-30 22:59:59');
        $this->settledPayment($this->booking($courtA), 8800, '2026-10-31 00:00:00');

        $revenue = app(ManagementReportQuery::class)->summary($manager, ReportFilters::forLocalDates('2026-10-01', '2026-10-30'))['revenue'];

        $this->assertSame(6500, $revenue['totalMinor']);
        $this->assertSame(3000, $revenue['byResource']['resource:'.$courtA->id]['amountMinor']);
        $this->assertSame(3500, $revenue['byResource']['resource:'.$courtB->id]['amountMinor']);
        $this->assertSame('Annex / Court 1', $revenue['byResource']['resource:'.$courtB->id]['label']);
        $this->assertSame(3500, $revenue['byFacility']['facility:'.$annex->id]['amountMinor']);
        $this->assertSame(['2026-10-02', '2026-10-05'], array_keys($revenue['byPeriod']));
        $this->assertSame(3500, $revenue['byPeriod']['2026-10-05']['amountMinor']);
    }

    public function test_revenue_keeps_same_named_personal_customers_apart_and_attributes_organisation_bookings(): void
    {
        [$manager, , $resource] = $this->managerVenue('Riverside');
        $firstSam = User::factory()->create(['name' => 'Sam Smith']);
        $secondSam = User::factory()->create(['name' => 'Sam Smith']);
        $organisation = Organisation::factory()->create(['name' => 'Sam Smith']);
        $this->settledPayment($this->booking($resource, ['customer_id' => $firstSam->id]), 1000, '2026-10-06 09:00:00');
        $this->settledPayment($this->booking($resource, ['customer_id' => $secondSam->id]), 2000, '2026-10-06 09:00:00');
        $this->settledPayment($this->booking($resource, ['customer_id' => $firstSam->id, 'organisation_id' => $organisation->id]), 4000, '2026-10-06 09:00:00');

        $revenue = app(ManagementReportQuery::class)->summary($manager, ReportFilters::forLocalDates('2026-10-01', '2026-10-31'))['revenue'];

        $this->assertSame(1000, $revenue['byCustomer']['customer:'.$firstSam->id]['amountMinor']);
        $this->assertSame(2000, $revenue['byCustomer']['customer:'.$secondSam->id]['amountMinor']);
        $this->assertSame(['organisation:'.$organisation->id => ['label' => 'Sam Smith', 'amountMinor' => 4000]], $revenue['byOrganisation']);
    }

    public function test_booking_counts_use_local_booked_start_date_boundaries(): void
    {
        [$manager, , $resource] = $this->managerVenue('Riverside');
        $this->booking($resource, ['starts_at' => '2026-10-01 00:30:00', 'ends_at' => '2026-10-01 01:30:00']);
        $this->booking($resource, ['starts_at' => '2026-10-31 23:30:00', 'ends_at' => '2026-11-01 00:30:00', 'status' => BookingStatus::Cancelled]);
        $this->booking($resource, ['starts_at' => '2026-09-30 22:30:00', 'ends_at' => '2026-09-30 23:30:00']);
        $this->booking($resource, ['starts_at' => '2026-11-01 00:30:00', 'ends_at' => '2026-11-01 01:30:00', 'status' => BookingStatus::Rejected]);
        $this->booking($resource, ['status' => BookingStatus::Requested]);
        $this->booking($resource, ['status' => BookingStatus::Rejected]);

        $bookings = app(ManagementReportQuery::class)->summary($manager, ReportFilters::forLocalDates('2026-10-01', '2026-10-31'))['bookings'];

        $this->assertSame(['confirmed' => 1, 'cancelled' => 1, 'rejected' => 1, 'noShow' => 0], $bookings);
    }

    public function test_finance_reports_paid_in_period_and_current_outstanding_and_overdue_exposure(): void
    {
        [$manager, , $resource] = $this->managerVenue('Riverside');
        $this->invoiceFor($this->booking($resource), ['status' => InvoiceStatus::Paid, 'paid_at' => '2026-10-03 10:00:00', 'total_minor' => 1000]);
        $this->invoiceFor($this->booking($resource), ['status' => InvoiceStatus::Paid, 'paid_at' => '2026-09-20 10:00:00', 'total_minor' => 9000]);
        $this->invoiceFor($this->booking($resource), ['issue_date' => '2026-10-02', 'due_date' => '2026-11-01', 'total_minor' => 2000]);
        $this->invoiceFor($this->booking($resource), ['issue_date' => '2026-08-01', 'due_date' => '2026-08-31', 'total_minor' => 3000]);
        $this->invoiceFor($this->booking($resource), ['issue_date' => '2026-10-06', 'due_date' => '2026-11-05', 'total_minor' => 7000]);
        [, , $hillsideResource] = $this->managerVenue('Hillside');
        $this->invoiceFor($this->booking($hillsideResource), ['issue_date' => '2026-08-01', 'due_date' => '2026-08-31', 'total_minor' => 8000]);

        $finance = app(ManagementReportQuery::class)->summary($manager, ReportFilters::forLocalDates('2026-10-01', '2026-10-05'))['finance'];

        $this->assertSame(['paidCount' => 1, 'paidMinor' => 1000, 'outstandingCount' => 2, 'outstandingMinor' => 5000, 'overdueCount' => 1, 'overdueMinor' => 3000], $finance);
    }

    public function test_finance_facility_filter_only_counts_matching_invoice_lines(): void
    {
        [$manager, $riverside, $hallCourt] = $this->managerVenue('Riverside');
        $annexCourt = Resource::factory()->create(['facility_id' => Facility::factory()->create(['centre_id' => $riverside->id])->id]);
        $invoice = Invoice::factory()->create(['issue_date' => '2026-10-02', 'due_date' => '2026-10-04', 'total_minor' => 5000]);
        InvoiceLine::factory()->for($invoice)->for($this->booking($hallCourt))->create(['amount_minor' => 2000]);
        InvoiceLine::factory()->for($invoice)->for($this->booking($annexCourt))->create(['amount_minor' => 3000]);

        $finance = app(ManagementReportQuery::class)->summary($manager, ReportFilters::forLocalDates('2026-10-01', '2026-10-31', facilityId: $hallCourt->facility_id))['finance'];

        $this->assertSame(1, $finance['overdueCount']);
        $this->assertSame(2000, $finance['overdueMinor']);
        $this->assertSame(2000, $finance['outstandingMinor']);
    }

    public function test_centre_filter_narrows_every_metric_to_one_assigned_centre(): void
    {
        [$manager, $riverside, $riversideCourt] = $this->managerVenue('Riverside');
        [, $hillside, $hillsideCourt] = $this->managerVenue('Hillside');
        $manager->assignedCentres()->attach($hillside);
        $this->settledPayment($this->booking($riversideCourt), 1000, '2026-10-06 09:00:00');
        $this->settledPayment($this->booking($hillsideCourt), 2000, '2026-10-06 09:00:00');
        $this->booking($hillsideCourt, ['status' => BookingStatus::Cancelled]);
        $this->invoiceFor($this->booking($hillsideCourt), ['issue_date' => '2026-10-02', 'total_minor' => 4000]);

        $query = app(ManagementReportQuery::class);
        $all = $query->summary($manager, ReportFilters::forLocalDates('2026-10-01', '2026-10-31'));
        $riversideOnly = $query->summary($manager, $this->filters($riverside));

        $this->assertSame(3000, $all['revenue']['totalMinor']);
        $this->assertSame(1, $all['bookings']['cancelled']);
        $this->assertSame(4000, $all['finance']['outstandingMinor']);
        $this->assertSame(1000, $riversideOnly['revenue']['totalMinor']);
        $this->assertSame(['centre:'.$riverside->id], array_keys($riversideOnly['revenue']['byCentre']));
        $this->assertSame(['confirmed' => 1, 'cancelled' => 0, 'rejected' => 0, 'noShow' => 0], $riversideOnly['bookings']);
        $this->assertSame(0, $riversideOnly['finance']['outstandingCount']);
    }

    public function test_utilisation_respects_resource_filter_date_boundaries_and_full_day_closures(): void
    {
        [$manager, $riverside, $court] = $this->managerVenue('Riverside');
        $otherCourt = Resource::factory()->create(['facility_id' => $court->facility_id]);
        foreach ([DayOfWeek::Monday, DayOfWeek::Tuesday, DayOfWeek::Wednesday] as $day) {
            FacilityBookableHour::factory()->for($court->facility)->create(['day_of_week' => $day, 'opens_at' => '09:00:00', 'closes_at' => '17:00:00']);
            foreach ([$court, $otherCourt] as $resource) {
                ResourceBookableHour::factory()->for($resource)->create(['day_of_week' => $day, 'opens_at' => '09:00:00', 'closes_at' => '17:00:00']);
            }
        }
        $this->booking($court, ['starts_at' => '2026-10-05 09:00:00', 'ends_at' => '2026-10-05 15:00:00']);
        $this->booking($court, ['starts_at' => '2026-10-06 09:00:00', 'ends_at' => '2026-10-06 11:00:00']);
        $this->booking($court, ['starts_at' => '2026-10-06 12:00:00', 'ends_at' => '2026-10-06 13:00:00', 'status' => BookingStatus::Cancelled]);
        AvailabilityBlock::factory()->forCentre($riverside)->create(['starts_at' => '2026-10-06 23:00:00', 'ends_at' => '2026-10-07 23:00:00']);

        $rows = app(ManagementReportQuery::class)->summary($manager, ReportFilters::forLocalDates('2026-10-06', '2026-10-07', resourceId: $court->id))['utilisation'];

        $this->assertCount(1, $rows);
        $this->assertSame('2026-10-06', $rows[0]['date']);
        $this->assertSame($court->name, $rows[0]['resource']);
        $this->assertSame(480, $rows[0]['availableMinutes']);
        $this->assertSame(120, $rows[0]['bookedMinutes']);
        $this->assertSame(25.0, $rows[0]['utilisationPercent']);
    }

    public function test_utilisation_facility_filter_excludes_other_facilities(): void
    {
        [$manager, $riverside, $court] = $this->managerVenue('Riverside');
        $annexCourt = Resource::factory()->create(['facility_id' => Facility::factory()->create(['centre_id' => $riverside->id])->id]);
        foreach ([$court, $annexCourt] as $resource) {
            FacilityBookableHour::factory()->for($resource->facility)->create(['day_of_week' => DayOfWeek::Tuesday, 'opens_at' => '09:00:00', 'closes_at' => '17:00:00']);
            ResourceBookableHour::factory()->for($resource)->create(['day_of_week' => DayOfWeek::Tuesday, 'opens_at' => '09:00:00', 'closes_at' => '17:00:00']);
        }

        $rows = app(ManagementReportQuery::class)->summary($manager, ReportFilters::forLocalDates('2026-10-06', '2026-10-06', facilityId: $annexCourt->facility_id))['utilisation'];

        $this->assertSame([$annexCourt->name], array_column($rows, 'resource'));
    }

    public function test_utilisation_table_shows_ten_rows_per_page_and_pages_within_active_filters(): void
    {
        [$manager, $riverside, $hallCourt] = $this->managerVenue('Riverside');
        $annexCourt = Resource::factory()->create(['facility_id' => Facility::factory()->create(['centre_id' => $riverside->id, 'name' => 'Annex'])->id, 'name' => 'Annex Court']);
        $this->openEveryDay($hallCourt);
        $this->openEveryDay($annexCourt);
        $this->actingAs($manager);

        $page = Livewire::test(Reporting::class)
            ->set('startDate', '2026-10-01')->set('endDate', '2026-10-30')
            ->assertViewHas('utilisation', fn ($rows): bool => $rows->total() === 60 && $rows->count() === 10 && $rows->currentPage() === 1)
            ->set('facilityId', $annexCourt->facility_id)
            ->assertViewHas('utilisation', fn ($rows): bool => $rows->total() === 30 && $rows->count() === 10)
            ->call('nextPage', Reporting::UTILISATION_PAGE_NAME);

        $page->assertSet('facilityId', $annexCourt->facility_id)
            ->assertSet('startDate', '2026-10-01')->assertSet('endDate', '2026-10-30')
            ->assertViewHas('utilisation', fn ($rows): bool => $rows->currentPage() === 2
                && $rows->count() === 10
                && $rows->total() === 30
                && collect($rows->items())->every(fn (array $row): bool => $row['resource'] === 'Annex Court')
                && $rows->first()['date'] === '2026-10-11');
    }

    public function test_changing_a_report_filter_returns_utilisation_to_the_first_page(): void
    {
        [$manager, , $court] = $this->managerVenue('Riverside');
        $this->openEveryDay($court);
        $this->actingAs($manager);

        Livewire::test(Reporting::class)
            ->set('startDate', '2026-10-01')->set('endDate', '2026-10-30')
            ->call('nextPage', Reporting::UTILISATION_PAGE_NAME)
            ->assertViewHas('utilisation', fn ($rows): bool => $rows->currentPage() === 2)
            ->set('endDate', '2026-10-29')
            ->assertViewHas('utilisation', fn ($rows): bool => $rows->currentPage() === 1 && $rows->total() === 29);
    }

    public function test_paginated_utilisation_never_includes_unassigned_centres(): void
    {
        [$manager, , $riversideCourt] = $this->managerVenue('Riverside');
        [, , $hillsideCourt] = $this->managerVenue('Hillside');
        $this->openEveryDay($riversideCourt);
        $this->openEveryDay($hillsideCourt);
        $this->actingAs($manager);

        Livewire::test(Reporting::class)
            ->set('startDate', '2026-10-01')->set('endDate', '2026-10-30')
            ->call('nextPage', Reporting::UTILISATION_PAGE_NAME)
            ->assertViewHas('utilisation', fn ($rows): bool => $rows->total() === 30
                && collect($rows->items())->every(fn (array $row): bool => $row['centre'] === 'Riverside'))
            ->assertDontSee('Hillside Court');
    }

    public function test_restricted_manager_cannot_query_or_render_another_centre(): void
    {
        [$manager] = $this->managerVenue('Riverside');
        $hillside = Centre::factory()->create(['name' => 'Hillside']);

        try {
            app(ManagementReportQuery::class)->summary($manager, $this->filters($hillside));
            $this->fail('An out-of-scope centre filter should be rejected.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $this->actingAs($manager)->get(Reporting::getUrl(parameters: ['centreId' => $hillside->id], panel: 'management'))->assertForbidden();
    }

    public function test_forged_facility_and_resource_filters_from_another_centre_are_rejected(): void
    {
        [$manager] = $this->managerVenue('Riverside');
        [, , $hillsideCourt] = $this->managerVenue('Hillside');
        $query = app(ManagementReportQuery::class);

        foreach ([['facilityId' => $hillsideCourt->facility_id], ['resourceId' => $hillsideCourt->id]] as $forged) {
            try {
                $query->bookingExportQuery($manager, ReportFilters::forLocalDates('2026-10-01', '2026-10-31', ...$forged));
                $this->fail('An out-of-scope filter should be rejected.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }

        $this->actingAs($manager);
        Livewire::test(Reporting::class)->set('resourceId', $hillsideCourt->id)->assertForbidden();
    }

    public function test_reporting_page_is_available_to_authorised_managers_and_not_customers(): void
    {
        [$manager] = $this->managerVenue('Riverside');
        $this->actingAs($manager)->get(Reporting::getUrl(panel: 'management'))->assertOk()->assertSee('Revenue')->assertSee('Utilisation');
        Livewire::test(Reporting::class)->assertSee('Export bookings');

        $customer = User::factory()->create();
        $customer->assignRole('customer');
        $this->actingAs($customer)->get(Reporting::getUrl(panel: 'management'))->assertForbidden();
    }

    public function test_invalid_or_unbounded_dates_show_errors_and_block_exports(): void
    {
        Storage::fake('local');
        [$manager] = $this->managerVenue('Riverside');
        $exports = ExportAction::fake();
        $this->actingAs($manager);

        Livewire::test(Reporting::class)
            ->set('startDate', '2026-10-10')->set('endDate', '2026-10-01')
            ->assertSee('The end date field must be a date after or equal to start date.')
            ->callAction('exportBookings');
        Livewire::test(Reporting::class)
            ->set('startDate', '2024-01-01')->set('endDate', '2026-10-01')
            ->assertSee('may not exceed 366 days')
            ->callAction('exportRevenue');

        $exports->assertNothingDispatched();
    }

    public function test_export_actions_dispatch_queries_matching_active_filters_and_centre_scope(): void
    {
        Storage::fake('local');
        [$manager, $riverside, $court] = $this->managerVenue('Riverside');
        [, , $hillsideCourt] = $this->managerVenue('Hillside');
        $annexCourt = Resource::factory()->create(['facility_id' => Facility::factory()->create(['centre_id' => $riverside->id])->id]);
        $inScope = $this->booking($court);
        $this->booking($court, ['starts_at' => '2026-09-20 10:00:00', 'ends_at' => '2026-09-20 11:00:00']);
        $otherFacility = $this->booking($annexCourt);
        $hillsideBooking = $this->booking($hillsideCourt);
        $payment = $this->settledPayment($inScope, 1000, '2026-10-06 09:00:00');
        $this->settledPayment($otherFacility, 1000, '2026-10-06 09:00:00');
        $this->settledPayment($hillsideBooking, 1000, '2026-10-06 09:00:00');
        $invoice = $this->invoiceFor($this->booking($court), ['issue_date' => '2026-10-02']);
        $this->invoiceFor($hillsideBooking, ['issue_date' => '2026-10-02']);
        $exports = ExportAction::fake();
        $this->actingAs($manager);

        Livewire::test(Reporting::class)
            ->set('startDate', '2026-10-01')->set('endDate', '2026-10-31')
            ->set('centreId', $riverside->id)->set('facilityId', $court->facility_id)
            ->callAction('exportBookings')
            ->callAction('exportRevenue')
            ->callAction('exportFinance');

        $ownedBy = fn (Export $export): bool => $export->user_id === $manager->id && $export->file_disk === 'local';
        $exports->assertDispatched(BookingExporter::class, fn (Export $export, Builder $query): bool => $ownedBy($export)
            && $query->pluck('id')->sort()->values()->all() === [$inScope->id, $invoice->lines()->sole()->booking_id]);
        $exports->assertDispatched(PaymentExporter::class, fn (Export $export, Builder $query): bool => $ownedBy($export) && $query->pluck('id')->all() === [$payment->id]);
        $exports->assertDispatched(InvoiceExporter::class, fn (Export $export, Builder $query): bool => $ownedBy($export) && $query->pluck('id')->all() === [$invoice->id]);
    }

    public function test_exporter_headings_and_rows_distinguish_organisation_and_personal_records(): void
    {
        [, , $resource] = $this->managerVenue('Riverside');
        $contact = User::factory()->create(['name' => 'Club Contact']);
        $organisation = Organisation::factory()->create(['name' => '=Riverside Juniors']);
        $organisationBooking = $this->booking($resource, ['customer_id' => $contact->id, 'organisation_id' => $organisation->id, 'reference' => 'BKG-ORG']);
        BookingPriceSnapshot::factory()->for($organisationBooking)->create(['resource_amount_minor' => 7050, 'subtotal_minor' => 7050, 'calculated_total_minor' => 7050, 'final_total_minor' => 7050]);
        $personalBooking = $this->booking($resource, ['customer_id' => User::factory()->create(['name' => 'Pat Personal'])->id]);
        $payment = $this->settledPayment($personalBooking, 2500, '2026-10-06 09:30:00');
        $invoice = $this->invoiceFor($organisationBooking, ['customer_id' => $contact->id, 'organisation_id' => $organisation->id, 'issue_date' => '2026-08-01', 'due_date' => '2026-08-31', 'total_minor' => 7050]);

        $headings = array_map(fn ($column): string => $column->getLabel(), BookingExporter::getColumns());
        $bookingRow = array_combine($headings, BookingExporter::test()->export($organisationBooking->fresh()));
        $paymentRow = array_combine(
            array_map(fn ($column): string => $column->getLabel(), PaymentExporter::getColumns()),
            PaymentExporter::test()->export($payment->fresh()),
        );
        $invoiceRow = array_combine(
            array_map(fn ($column): string => $column->getLabel(), InvoiceExporter::getColumns()),
            InvoiceExporter::test()->export($invoice->fresh()),
        );

        $this->assertSame(['Booking reference', 'Starts (Europe/London)', 'Ends (Europe/London)', 'Status', 'Attendance', 'Centre', 'Facility', 'Resource', 'Account type', 'Organisation', 'Customer', 'Currency', 'Historic booking total'], $headings);
        $this->assertSame('BKG-ORG', $bookingRow['Booking reference']);
        $this->assertSame('2026-10-06 11:00', $bookingRow['Starts (Europe/London)']);
        $this->assertSame('Organisation', $bookingRow['Account type']);
        $this->assertSame("'=Riverside Juniors", $bookingRow['Organisation']);
        $this->assertSame('Club Contact', $bookingRow['Customer']);
        $this->assertSame('GBP', $bookingRow['Currency']);
        $this->assertSame('70.50', $bookingRow['Historic booking total']);
        $this->assertSame('Personal', $paymentRow['Account type']);
        $this->assertEmpty($paymentRow['Organisation']);
        $this->assertSame('Pat Personal', $paymentRow['Customer']);
        $this->assertSame('2026-10-06 10:30', $paymentRow['Settled (Europe/London)']);
        $this->assertSame('25.00', $paymentRow['Amount']);
        $this->assertSame('Organisation', $invoiceRow['Account type']);
        $this->assertSame('Yes', $invoiceRow['Overdue']);
        $this->assertSame('2026-08-31', $invoiceRow['Due date']);
        $this->assertSame('Riverside', $invoiceRow['Centre']);
        $this->assertSame('70.50', $invoiceRow['Invoice total']);
    }

    public function test_synchronous_export_writes_a_private_file_for_in_scope_rows(): void
    {
        Storage::fake('local');
        [$manager, , $court] = $this->managerVenue('Riverside');
        [, , $hillsideCourt] = $this->managerVenue('Hillside');
        $this->booking($court);
        $this->booking($court, ['status' => BookingStatus::Cancelled]);
        $this->booking($hillsideCourt);
        $this->actingAs($manager);

        Livewire::test(Reporting::class)->set('startDate', '2026-10-01')->set('endDate', '2026-10-31')->callAction('exportBookings');

        $export = Export::query()->sole();
        $this->assertTrue($export->user->is($manager));
        $this->assertSame('local', $export->file_disk);
        $this->assertSame(storage_path('app/private'), config('filesystems.disks.local.root'));
        $this->assertSame(2, $export->total_rows);
        $this->assertSame(2, $export->successful_rows);
        $this->assertNotNull($export->completed_at);
        Storage::disk('local')->assertExists($export->getFileDirectory().'/'.$export->file_name.'.xlsx');
    }

    public function test_exports_are_queued_when_a_queue_worker_is_configured(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();
        Storage::fake('local');
        [$manager, , $court] = $this->managerVenue('Riverside');
        $this->booking($court);
        $this->actingAs($manager);

        Livewire::test(Reporting::class)->set('startDate', '2026-10-01')->set('endDate', '2026-10-31')
            ->callAction('exportBookings')
            ->assertNotified('Export started');

        Queue::assertPushed(ChainedBatch::class);
        $this->assertNull(Export::query()->sole()->completed_at);
        $this->assertTrue(Filament::getPanel('management')->hasDatabaseNotifications());
    }

    public function test_only_the_exporting_manager_can_download_the_private_file(): void
    {
        Storage::fake('local');
        [$owner] = $this->managerVenue('Riverside');
        [$otherManager] = $this->managerVenue('Hillside');
        $export = new Export;
        $export->forceFill(['user_id' => $owner->id, 'exporter' => BookingExporter::class, 'file_disk' => 'local', 'file_name' => 'bookings', 'total_rows' => 1, 'successful_rows' => 1, 'completed_at' => now()])->save();
        Storage::disk('local')->put($export->getFileDirectory().'/bookings.xlsx', 'xlsx-bytes');
        $url = route('filament.exports.download', ['export' => $export, 'format' => 'xlsx']);

        $this->get($url)->assertUnauthorized();
        $this->actingAs($otherManager)->get($url)->assertForbidden();
        $this->actingAs($owner)->get($url)->assertOk()->assertDownload('bookings.xlsx');
    }

    /** @return array{User, Centre, resource} */
    private function managerVenue(string $name): array
    {
        $centre = Centre::factory()->create(['name' => $name]);
        $facility = Facility::factory()->create(['centre_id' => $centre->id, 'name' => $name.' Hall']);
        $resource = Resource::factory()->create(['facility_id' => $facility->id, 'name' => $name.' Court']);
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->assignedCentres()->attach($centre);

        return [$manager, $centre, $resource];
    }

    /** @param array<string, mixed> $attributes */
    private function booking(Resource $resource, array $attributes = []): Booking
    {
        return Booking::factory()->for($resource)->create(['starts_at' => '2026-10-06 10:00:00', 'ends_at' => '2026-10-06 11:00:00', 'status' => BookingStatus::Confirmed, ...$attributes]);
    }

    private function settledPayment(Booking $booking, int $amountMinor, string $succeededAt): Payment
    {
        return Payment::factory()->for($booking)->create(['amount_minor' => $amountMinor, 'status' => PaymentStatus::Succeeded, 'succeeded_at' => $succeededAt]);
    }

    /** @param array<string, mixed> $attributes */
    private function invoiceFor(Booking $booking, array $attributes = []): Invoice
    {
        $invoice = Invoice::factory()->create(['customer_id' => $booking->customer_id, 'organisation_id' => $booking->organisation_id, 'total_minor' => 5000, ...$attributes]);
        InvoiceLine::factory()->for($invoice)->for($booking)->create(['amount_minor' => $invoice->total_minor]);

        return $invoice;
    }

    private function openEveryDay(Resource $resource): void
    {
        foreach (DayOfWeek::cases() as $day) {
            FacilityBookableHour::factory()->for($resource->facility)->create(['day_of_week' => $day, 'opens_at' => '09:00:00', 'closes_at' => '17:00:00']);
            ResourceBookableHour::factory()->for($resource)->create(['day_of_week' => $day, 'opens_at' => '09:00:00', 'closes_at' => '17:00:00']);
        }
    }

    private function filters(Centre $centre): ReportFilters
    {
        return ReportFilters::forLocalDates('2026-10-01', '2026-10-31', centreId: $centre->id);
    }
}
