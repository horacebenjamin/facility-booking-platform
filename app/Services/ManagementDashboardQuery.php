<?php

namespace App\Services;

use App\Enums\BillingMethod;
use App\Enums\BookingStatus;
use App\Enums\ClosureImpactStatus;
use App\Enums\FinancialStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OperationalIssueStatus;
use App\Models\AvailabilityBlock;
use App\Models\AvailabilityBlockBookingImpact;
use App\Models\Booking;
use App\Models\DamageReport;
use App\Models\Incident;
use App\Models\Invoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class ManagementDashboardQuery
{
    /**
     * @return array{
     *     pendingBookings: array{count: int, records: Collection<int, Booking>},
     *     awaitingPayment: array{count: int, records: Collection<int, Booking>},
     *     overdueInvoices: array{count: int, records: Collection<int, Invoice>},
     *     todaysBookings: array{count: int, records: Collection<int, Booking>},
     *     operationalIssues: array{count: int, records: Collection<int, DamageReport>},
     *     closureImpacts: array{count: int, records: Collection<int, AvailabilityBlockBookingImpact>},
     *     openIncidents: array{count: int, records: Collection<int, Incident>}
     * }
     */
    public function forManager(User $manager): array
    {
        if (! $manager->hasRole('manager')) {
            throw new AuthorizationException;
        }

        $centreIds = array_values($manager->assignedCentres()->pluck('centres.id')->map(fn (int $id): int => $id)->all());
        $canViewBookings = Gate::forUser($manager)->allows('viewAny', Booking::class);
        $canViewInvoices = Gate::forUser($manager)->allows('viewAny', Invoice::class);
        $canViewDamageReports = Gate::forUser($manager)->allows('viewAny', DamageReport::class);
        $canViewIncidents = Gate::forUser($manager)->allows('viewAny', Incident::class);
        $canViewClosures = Gate::forUser($manager)->allows('viewAny', AvailabilityBlock::class);
        $today = CarbonImmutable::now(config('app.timezone'));
        $localToday = CarbonImmutable::now((string) config('booking.local_timezone'));
        $localDayStart = $localToday->startOfDay();
        $dayStart = $localDayStart->utc();
        $dayEnd = $localDayStart->addDay()->utc();

        $pendingBookings = Booking::query()
            ->whereIn('centre_id', $centreIds)
            ->when(! $canViewBookings, fn (Builder $query): Builder => $query->whereKey([]))
            ->where('status', BookingStatus::Requested)
            ->with(['customer:id,name', 'organisation:id,name', 'centre:id,name', 'facility:id,name', 'resource:id,name'])
            ->orderBy('created_at');

        $awaitingPayment = Booking::query()
            ->whereIn('centre_id', $centreIds)
            ->when(! $canViewBookings, fn (Builder $query): Builder => $query->whereKey([]))
            ->where('status', BookingStatus::Approved)
            ->where('financial_status', FinancialStatus::AwaitingPayment)
            ->where('billing_method', BillingMethod::Card)
            ->where('payment_due_at', '>', $today)
            ->where('starts_at', '>', $today)
            ->with(['customer:id,name', 'organisation:id,name', 'centre:id,name', 'facility:id,name', 'resource:id,name', 'priceSnapshot']);

        $overdueInvoices = Invoice::query()
            ->when(! $canViewInvoices, fn (Builder $query): Builder => $query->whereKey([]))
            ->scopedToCentres($centreIds)
            ->where('status', InvoiceStatus::Issued)
            ->overdue()
            ->with(['customer:id,name', 'organisation:id,name', 'lines.booking.centre']);

        $todaysBookings = Booking::query()
            ->whereIn('centre_id', $centreIds)
            ->when(! $canViewBookings, fn (Builder $query): Builder => $query->whereKey([]))
            ->where('status', BookingStatus::Confirmed)
            ->where('starts_at', '>=', $dayStart)
            ->where('starts_at', '<', $dayEnd)
            ->with(['customer:id,name', 'organisation:id,name', 'centre:id,name', 'facility:id,name', 'resource:id,name'])
            ->orderBy('starts_at');

        $operationalIssues = DamageReport::query()
            ->whereIn('centre_id', $centreIds)
            ->when(! $canViewDamageReports, fn (Builder $query): Builder => $query->whereKey([]))
            ->whereIn('status', [OperationalIssueStatus::Open, OperationalIssueStatus::Reviewed])
            ->with(['centre:id,name', 'booking:id,reference', 'resource:id,name'])
            ->orderByDesc('observed_at');

        $closureImpacts = AvailabilityBlockBookingImpact::query()
            ->when(! $canViewClosures, fn (Builder $query): Builder => $query->whereKey([]))
            ->where('status', ClosureImpactStatus::Unresolved)
            ->whereHas('booking', fn (Builder $query): Builder => $query->whereIn('centre_id', $centreIds))
            ->whereHas('availabilityBlock', fn (Builder $query): Builder => $query->scopedToCentres($centreIds))
            ->with([
                'booking:id,reference,centre_id,facility_id,resource_id,customer_id,organisation_id,starts_at,ends_at',
                'booking.customer:id,name',
                'booking.organisation:id,name',
                'booking.centre:id,name',
                'booking.facility:id,name',
                'booking.resource:id,name',
                'availabilityBlock:id,centre_id,facility_id,resource_id,type,reason',
                'availabilityBlock.centre:id,name',
                'availabilityBlock.facility:id,name,centre_id',
                'availabilityBlock.resource:id,name,facility_id',
                'availabilityBlock.resource.facility:id,name,centre_id',
            ])
            ->orderByDesc('detected_at');

        $openIncidents = Incident::query()
            ->whereIn('centre_id', $centreIds)
            ->when(! $canViewIncidents, fn (Builder $query): Builder => $query->whereKey([]))
            ->whereIn('status', [OperationalIssueStatus::Open, OperationalIssueStatus::Reviewed])
            ->with(['centre:id,name', 'booking:id,reference', 'resource:id,name'])
            ->orderByDesc('occurred_at');

        return [
            'pendingBookings' => $this->section($pendingBookings, 5),
            'awaitingPayment' => $this->section($awaitingPayment, 5),
            'overdueInvoices' => $this->section($overdueInvoices, 5),
            'todaysBookings' => $this->section($todaysBookings, 5),
            'operationalIssues' => $this->section($operationalIssues, 5),
            'closureImpacts' => $this->section($closureImpacts, 5),
            'openIncidents' => $this->section($openIncidents, 5),
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return array{count: int, records: Collection<int, TModel>}
     */
    private function section(Builder $query, int $limit): array
    {
        return [
            'count' => (clone $query)->count(),
            'records' => (clone $query)->limit($limit)->get(),
        ];
    }
}
