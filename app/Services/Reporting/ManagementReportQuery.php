<?php

namespace App\Services\Reporting;

use App\Enums\AttendanceState;
use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\Centre;
use App\Models\Facility;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Resource;
use App\Models\User;
use App\Services\AvailabilityBlockScopeMatcher;
use App\Services\OperationalOccupancyCalculator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ManagementReportQuery
{
    public function __construct(
        private AvailabilityBlockScopeMatcher $blockScopeMatcher,
        private OperationalOccupancyCalculator $occupancyCalculator,
    ) {}

    /**
     * Revenue means settled card payments and paid invoice line amounts. It deliberately excludes
     * booking snapshots, outstanding invoices, rejected bookings and cancelled bookings without a settlement.
     * Revenue is attributed to its settlement date, so advance payments for later bookings count when collected.
     * Booking counts use the booked start date.
     *
     * Revenue groups are keyed by stable identifiers ("centre:12", "organisation:4", "2026-10-06") so
     * records sharing a display name are never merged.
     *
     * @return array{revenue: array{totalMinor: int, transactions: int, byCentre: array<string, array{label: string, amountMinor: int}>, byFacility: array<string, array{label: string, amountMinor: int}>, byResource: array<string, array{label: string, amountMinor: int}>, byCustomer: array<string, array{label: string, amountMinor: int}>, byOrganisation: array<string, array{label: string, amountMinor: int}>, byPeriod: array<string, array{label: string, amountMinor: int}>}, bookings: array{confirmed: int, cancelled: int, rejected: int, noShow: int}, finance: array{paidCount: int, paidMinor: int, outstandingCount: int, outstandingMinor: int, overdueCount: int, overdueMinor: int}, utilisation: list<array{date: string, centre: string, facility: string, resource: string, availableMinutes: int, bookedMinutes: int, utilisationPercent: float}>}
     */
    public function summary(User $manager, ReportFilters $filters): array
    {
        $centreIds = $this->authorisedCentreIds($manager);
        $this->assertFiltersAreInScope($filters, $centreIds);

        return [
            'revenue' => $this->revenue($filters, $centreIds),
            'bookings' => $this->bookings($filters, $centreIds),
            'finance' => $this->finance($filters, $centreIds),
            'utilisation' => $this->utilisation($filters, $centreIds),
        ];
    }

    /** @return Collection<int, Centre> */
    public function authorisedCentres(User $manager): Collection
    {
        $this->assertManager($manager);

        return $manager->assignedCentres()->orderBy('name')->get(['centres.id', 'centres.name']);
    }

    /** @return Builder<Booking> */
    public function bookingExportQuery(User $manager, ReportFilters $filters): Builder
    {
        $centreIds = $this->authorisedCentreIds($manager);
        $this->assertFiltersAreInScope($filters, $centreIds);

        return $this->applyBookingScope(Booking::query(), $filters, $centreIds)
            ->with(['customer:id,name', 'organisation:id,name', 'centre:id,name', 'facility:id,name', 'resource:id,name', 'priceSnapshot:id,booking_id,currency,final_total_minor']);
    }

    /** @return Builder<Invoice> */
    public function invoiceExportQuery(User $manager, ReportFilters $filters): Builder
    {
        $centreIds = $this->authorisedCentreIds($manager);
        $this->assertFiltersAreInScope($filters, $centreIds);

        return $this->financeInvoiceQuery($filters, $centreIds)
            ->with(['customer:id,name', 'organisation:id,name', 'lines.booking.centre:id,name', 'lines.booking.facility:id,name', 'lines.booking.resource:id,name']);
    }

    /** @return Builder<Payment> */
    public function paymentExportQuery(User $manager, ReportFilters $filters): Builder
    {
        $centreIds = $this->authorisedCentreIds($manager);
        $this->assertFiltersAreInScope($filters, $centreIds);

        return Payment::query()->where('status', PaymentStatus::Succeeded)
            ->whereNotNull('booking_id')
            ->where('succeeded_at', '>=', $filters->startsAt)->where('succeeded_at', '<', $filters->endsAt)
            ->whereHas('booking', fn (Builder $query): Builder => $this->applyBookingDimensions($query, $filters, $centreIds))
            ->with(['booking.customer:id,name', 'booking.organisation:id,name', 'booking.centre:id,name', 'booking.facility:id,name', 'booking.resource:id,name']);
    }

    /**
     * @param  list<int>  $centreIds
     * @return array{totalMinor: int, transactions: int, byCentre: array<string, array{label: string, amountMinor: int}>, byFacility: array<string, array{label: string, amountMinor: int}>, byResource: array<string, array{label: string, amountMinor: int}>, byCustomer: array<string, array{label: string, amountMinor: int}>, byOrganisation: array<string, array{label: string, amountMinor: int}>, byPeriod: array<string, array{label: string, amountMinor: int}>}
     */
    private function revenue(ReportFilters $filters, array $centreIds): array
    {
        $lines = collect();

        $cardPayments = Payment::query()
            ->where('status', PaymentStatus::Succeeded)
            ->whereNotNull('booking_id')
            ->where('succeeded_at', '>=', $filters->startsAt)->where('succeeded_at', '<', $filters->endsAt)
            ->with(['booking.customer:id,name', 'booking.organisation:id,name', 'booking.centre:id,name', 'booking.facility:id,name', 'booking.resource:id,name'])
            ->whereHas('booking', fn (Builder $query): Builder => $this->applyBookingDimensions($query, $filters, $centreIds))
            ->get();

        foreach ($cardPayments as $payment) {
            $booking = $payment->booking;
            if ($booking !== null) {
                $lines->push($this->revenueLine($booking, $payment->amount_minor, $payment->succeeded_at));
            }
        }

        $paidInvoices = Invoice::query()
            ->where('status', InvoiceStatus::Paid)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $filters->startsAt)->where('paid_at', '<', $filters->endsAt)
            ->scopedToCentres($centreIds)
            ->with(['lines.booking.customer:id,name', 'lines.booking.organisation:id,name', 'lines.booking.centre:id,name', 'lines.booking.facility:id,name', 'lines.booking.resource:id,name'])
            ->get();

        foreach ($paidInvoices as $invoice) {
            foreach ($invoice->lines as $line) {
                $booking = $line->booking;
                if ($booking !== null && $this->bookingMatchesFilters($booking, $filters, $centreIds)) {
                    $lines->push($this->revenueLine($booking, $line->amount_minor, $invoice->paid_at));
                }
            }
        }

        return [
            'totalMinor' => (int) $lines->sum('amountMinor'),
            'transactions' => $lines->count(),
            'byCentre' => $this->groupRevenue($lines, 'centre'),
            'byFacility' => $this->groupRevenue($lines, 'facility'),
            'byResource' => $this->groupRevenue($lines, 'resource'),
            'byCustomer' => $this->groupRevenue($lines, 'customer'),
            'byOrganisation' => $this->groupRevenue($lines, 'organisation'),
            'byPeriod' => $this->groupRevenue($lines, 'period', chronological: true),
        ];
    }

    /**
     * @param  list<int>  $centreIds
     * @return array{confirmed: int, cancelled: int, rejected: int, noShow: int}
     */
    private function bookings(ReportFilters $filters, array $centreIds): array
    {
        $query = Booking::query();
        $this->applyBookingScope($query, $filters, $centreIds);
        $bookings = $query->selectRaw('status, attendance_state, count(*) as aggregate_count')
            ->groupBy('status', 'attendance_state')
            ->get();

        return [
            'confirmed' => (int) $bookings->where('status', BookingStatus::Confirmed)->sum('aggregate_count'),
            'cancelled' => (int) $bookings->where('status', BookingStatus::Cancelled)->sum('aggregate_count'),
            'rejected' => (int) $bookings->where('status', BookingStatus::Rejected)->sum('aggregate_count'),
            'noShow' => (int) $bookings->where('attendance_state', AttendanceState::NoShow)->sum('aggregate_count'),
        ];
    }

    /**
     * Paid means invoices settled within the period. Outstanding is current exposure: issued, unpaid invoices
     * issued on or before the period end. Overdue is the outstanding subset past its due date, using the
     * invoice domain's authoritative overdue rule. Amounts only include invoice lines matching the active
     * centre/facility/resource/customer filters.
     *
     * @param  list<int>  $centreIds
     * @return array{paidCount: int, paidMinor: int, outstandingCount: int, outstandingMinor: int, overdueCount: int, overdueMinor: int}
     */
    private function finance(ReportFilters $filters, array $centreIds): array
    {
        $invoices = $this->financeInvoiceQuery($filters, $centreIds)
            ->with('lines.booking:id,centre_id,facility_id,resource_id,customer_id')
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'invoice' => $invoice,
                'amountMinor' => (int) $invoice->lines
                    ->filter(fn ($line): bool => $line->booking !== null && $this->bookingMatchesFilters($line->booking, $filters, $centreIds))
                    ->sum('amount_minor'),
            ]);

        $paid = $invoices->filter(fn (array $row): bool => $row['invoice']->status === InvoiceStatus::Paid);
        $outstanding = $invoices->filter(fn (array $row): bool => $row['invoice']->status === InvoiceStatus::Issued);
        $overdue = $outstanding->filter(fn (array $row): bool => $row['invoice']->isOverdue());

        return [
            'paidCount' => $paid->count(), 'paidMinor' => (int) $paid->sum('amountMinor'),
            'outstandingCount' => $outstanding->count(), 'outstandingMinor' => (int) $outstanding->sum('amountMinor'),
            'overdueCount' => $overdue->count(), 'overdueMinor' => (int) $overdue->sum('amountMinor'),
        ];
    }

    /**
     * Invoices relevant to the finance metrics: paid within the period, or still outstanding and issued by the period end.
     *
     * @param  list<int>  $centreIds
     * @return Builder<Invoice>
     */
    private function financeInvoiceQuery(ReportFilters $filters, array $centreIds): Builder
    {
        return Invoice::query()->scopedToCentres($centreIds)
            ->whereHas('lines.booking', fn (Builder $query): Builder => $this->applyBookingDimensions($query, $filters, $centreIds))
            ->where(fn (Builder $query): Builder => $query
                ->where(fn (Builder $paid): Builder => $paid->where('status', InvoiceStatus::Paid)->where('paid_at', '>=', $filters->startsAt)->where('paid_at', '<', $filters->endsAt))
                ->orWhere(fn (Builder $open): Builder => $open->where('status', InvoiceStatus::Issued)->whereDate('issue_date', '<=', $filters->localEndDate())));
    }

    /**
     * @param  list<int>  $centreIds
     * @return list<array{date: string, centre: string, facility: string, resource: string, availableMinutes: int, bookedMinutes: int, utilisationPercent: float}>
     */
    private function utilisation(ReportFilters $filters, array $centreIds): array
    {
        $resources = Resource::query()->where('is_active', true)
            ->whereHas('facility', fn (Builder $query): Builder => $query->where('is_active', true)->whereIn('centre_id', $centreIds)->whereHas('centre', fn (Builder $centres): Builder => $centres->where('is_active', true)))
            ->when($filters->centreId !== null, fn (Builder $query): Builder => $query->whereHas('facility', fn (Builder $facility): Builder => $facility->where('centre_id', $filters->centreId)))
            ->when($filters->facilityId !== null, fn (Builder $query): Builder => $query->where('facility_id', $filters->facilityId))
            ->when($filters->resourceId !== null, fn (Builder $query): Builder => $query->whereKey($filters->resourceId))
            ->with(['facility.centre:id,name', 'facility.bookableHours', 'bookableHours'])
            ->get();
        $bookings = Booking::query()->where('status', BookingStatus::Confirmed)
            ->where('starts_at', '<', $filters->endsAt)->where('ends_at', '>', $filters->startsAt)
            ->whereIn('resource_id', $resources->modelKeys())->get()->groupBy('resource_id');
        $day = $filters->startsAt->setTimezone(ReportFilters::TIMEZONE)->startOfDay();
        $endDay = $filters->endsAt->setTimezone(ReportFilters::TIMEZONE)->startOfDay();
        $rows = [];

        while ($day->lt($endDay)) {
            foreach ($resources as $resource) {
                $availableMinutes = $this->availableMinutes($resource, $day);
                if ($availableMinutes === 0) {
                    continue;
                }
                $bookedMinutes = $this->bookedMinutes($resource, $bookings->get($resource->id, collect()), $day);
                $bookedMinutes = min($bookedMinutes, $availableMinutes);
                $rows[] = ['date' => $day->toDateString(), 'centre' => $resource->facility->centre->name, 'facility' => $resource->facility->name, 'resource' => $resource->name, 'availableMinutes' => $availableMinutes, 'bookedMinutes' => $bookedMinutes, 'utilisationPercent' => round(($bookedMinutes / $availableMinutes) * 100, 1)];
            }
            $day = $day->addDay();
        }

        return $rows;
    }

    /**
     * @param  Collection<int, array{amountMinor: int, groups: array<string, array{key: string, label: string}|null>}>  $lines
     * @return array<string, array{label: string, amountMinor: int}>
     */
    private function groupRevenue(Collection $lines, string $dimension, bool $chronological = false): array
    {
        $groups = $lines->filter(fn (array $line): bool => $line['groups'][$dimension] !== null)
            ->groupBy(fn (array $line): string => $line['groups'][$dimension]['key'] ?? '')
            ->map(fn (Collection $group): array => [
                'label' => $group->first()['groups'][$dimension]['label'] ?? '',
                'amountMinor' => (int) $group->sum('amountMinor'),
            ]);

        return ($chronological ? $groups->sortKeys() : $groups->sortByDesc('amountMinor'))->all();
    }

    /**
     * Organisation-owned bookings are attributed to the organisation; personal bookings to the customer.
     *
     * @return array{amountMinor: int, groups: array<string, array{key: string, label: string}|null>}
     */
    private function revenueLine(Booking $booking, int $amountMinor, ?CarbonInterface $paidAt): array
    {
        $period = $paidAt?->setTimezone(ReportFilters::TIMEZONE)->toDateString() ?? 'unknown';
        $organisation = $booking->organisation;

        return ['amountMinor' => $amountMinor, 'groups' => [
            'centre' => ['key' => 'centre:'.$booking->centre_id, 'label' => $booking->centre->name],
            'facility' => ['key' => 'facility:'.$booking->facility_id, 'label' => $booking->centre->name.' / '.$booking->facility->name],
            'resource' => ['key' => 'resource:'.$booking->resource_id, 'label' => $booking->facility->name.' / '.$booking->resource->name],
            'customer' => $organisation === null ? ['key' => 'customer:'.$booking->customer_id, 'label' => $booking->customer->name] : null,
            'organisation' => $organisation !== null ? ['key' => 'organisation:'.$organisation->id, 'label' => $organisation->name] : null,
            'period' => ['key' => $period, 'label' => $period],
        ]];
    }

    private function availableMinutes(Resource $resource, CarbonImmutable $day): int
    {
        $facilityHour = $resource->facility->bookableHours->first(fn ($hour): bool => $hour->day_of_week->value === $day->isoWeekday());
        $resourceHour = $resource->bookableHours->first(fn ($hour): bool => $hour->day_of_week->value === $day->isoWeekday());
        if ($facilityHour === null || $resourceHour === null) {
            return 0;
        }
        $startsAt = $day->setTimeFromTimeString(max($facilityHour->opens_at, $resourceHour->opens_at));
        $endsAt = $day->setTimeFromTimeString(min($facilityHour->closes_at, $resourceHour->closes_at));
        if (! $startsAt->lt($endsAt)) {
            return 0;
        }

        return max(0, (int) $startsAt->diffInMinutes($endsAt) - intdiv($this->blockedSeconds($resource, $startsAt, $endsAt), 60));
    }

    /** @param Collection<int, Booking> $bookings */
    private function bookedMinutes(Resource $resource, Collection $bookings, CarbonImmutable $day): int
    {
        $dayEnd = $day->addDay();

        return (int) $bookings->sum(function (Booking $booking) use ($resource, $day, $dayEnd): int {
            $occupancy = $this->occupancyCalculator->calculate($resource, $booking->starts_at, $booking->ends_at);
            if ($occupancy === null) {
                return 0;
            }
            $startsAt = $occupancy->startsAt->setTimezone(ReportFilters::TIMEZONE)->max($day);
            $endsAt = $occupancy->endsAt->setTimezone(ReportFilters::TIMEZONE)->min($dayEnd);

            return $startsAt->lt($endsAt) ? (int) $startsAt->diffInMinutes($endsAt) : 0;
        });
    }

    private function blockedSeconds(Resource $resource, CarbonImmutable $startsAt, CarbonImmutable $endsAt): int
    {
        $ranges = $this->blockScopeMatcher->blocksFor($resource)->where('starts_at', '<', $endsAt->utc())->where('ends_at', '>', $startsAt->utc())->get()
            ->map(fn (AvailabilityBlock $block): array => ['start' => CarbonImmutable::instance($block->starts_at)->max($startsAt->utc()), 'end' => $block->effectiveEndsAt()->min($endsAt->utc())])
            ->filter(fn (array $range): bool => $range['start']->lt($range['end']))->sortBy('start')->values();
        $seconds = 0;
        $current = null;
        foreach ($ranges as $range) {
            if ($current === null) {
                $current = $range;

                continue;
            }
            if ($range['start']->lte($current['end'])) {
                $current['end'] = $current['end']->max($range['end']);

                continue;
            }
            $seconds += $current['start']->diffInSeconds($current['end']);
            $current = $range;
        }
        if ($current !== null) {
            $seconds += $current['start']->diffInSeconds($current['end']);
        }

        return (int) $seconds;
    }

    /**
     * @param  Builder<Booking>  $query
     * @param  list<int>  $centreIds
     * @return Builder<Booking>
     */
    private function applyBookingScope(Builder $query, ReportFilters $filters, array $centreIds): Builder
    {
        return $this->applyBookingDimensions($query, $filters, $centreIds)
            ->where('starts_at', '>=', $filters->startsAt)->where('starts_at', '<', $filters->endsAt);
    }

    /**
     * Applies centre scope and the centre/facility/resource/customer filters without a booking date constraint.
     *
     * @param  Builder<Booking>  $query
     * @param  list<int>  $centreIds
     * @return Builder<Booking>
     */
    private function applyBookingDimensions(Builder $query, ReportFilters $filters, array $centreIds): Builder
    {
        return $query->whereIn('centre_id', $centreIds)
            ->when($filters->centreId !== null, fn (Builder $query): Builder => $query->where('centre_id', $filters->centreId))
            ->when($filters->facilityId !== null, fn (Builder $query): Builder => $query->where('facility_id', $filters->facilityId))
            ->when($filters->resourceId !== null, fn (Builder $query): Builder => $query->where('resource_id', $filters->resourceId))
            ->when($filters->customerId !== null, fn (Builder $query): Builder => $query->where('customer_id', $filters->customerId));
    }

    /** @param list<int> $centreIds */
    private function bookingMatchesFilters(Booking $booking, ReportFilters $filters, array $centreIds): bool
    {
        return in_array($booking->centre_id, $centreIds, true) && ($filters->centreId === null || $booking->centre_id === $filters->centreId) && ($filters->facilityId === null || $booking->facility_id === $filters->facilityId) && ($filters->resourceId === null || $booking->resource_id === $filters->resourceId) && ($filters->customerId === null || $booking->customer_id === $filters->customerId);
    }

    /** @return list<int> */
    private function authorisedCentreIds(User $manager): array
    {
        $this->assertManager($manager);

        return array_values($manager->assignedCentres()->pluck('centres.id')->map(fn (int $id): int => $id)->all());
    }

    /** @param list<int> $centreIds */
    private function assertFiltersAreInScope(ReportFilters $filters, array $centreIds): void
    {
        if ($filters->centreId !== null && ! in_array($filters->centreId, $centreIds, true)) {
            throw new AuthorizationException;
        }
        if ($filters->facilityId !== null && ! Facility::query()->whereKey($filters->facilityId)->whereIn('centre_id', $centreIds)->exists()) {
            throw new AuthorizationException;
        }
        if ($filters->resourceId !== null && ! Resource::query()->whereKey($filters->resourceId)->whereHas('facility', fn (Builder $query): Builder => $query->whereIn('centre_id', $centreIds))->exists()) {
            throw new AuthorizationException;
        }
    }

    private function assertManager(User $manager): void
    {
        if (! $manager->hasRole('manager') || ! $manager->can('bookings.view')) {
            throw new AuthorizationException;
        }
    }
}
