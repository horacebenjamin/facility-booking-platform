<?php

namespace App\Filament\Pages;

use App\Filament\Exports\BookingExporter;
use App\Filament\Exports\InvoiceExporter;
use App\Filament\Exports\PaymentExporter;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\User;
use App\Services\Reporting\ManagementReportQuery;
use App\Services\Reporting\ReportFilters;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use UnitEnum;

class Reporting extends Page
{
    use WithPagination;

    public const int UTILISATION_PER_PAGE = 10;

    public const string UTILISATION_PAGE_NAME = 'utilisationPage';

    /** Report periods longer than this are summarised by week in the utilisation trend. */
    private const int DAILY_TREND_MAX_DAYS = 62;

    private const array FILTER_PROPERTIES = ['startDate', 'endDate', 'centreId', 'facilityId', 'resourceId'];

    protected static ?string $navigationLabel = 'Reporting';

    protected static string|UnitEnum|null $navigationGroup = 'Management';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.reporting';

    protected ?string $subheading = 'Understand revenue, bookings, financial exposure and utilisation across your centres.';

    #[Url]
    public string $startDate = '';

    #[Url]
    public string $endDate = '';

    #[Url]
    public ?int $centreId = null;

    #[Url]
    public ?int $facilityId = null;

    #[Url]
    public ?int $resourceId = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->hasRole('manager')
            && $user->can('bookings.view');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $today = CarbonImmutable::now(ReportFilters::timezone());
        $this->startDate = $this->startDate === '' ? $today->subDays(29)->toDateString() : $this->startDate;
        $this->endDate = $this->endDate === '' ? $today->toDateString() : $this->endDate;
    }

    public function refreshReport(): void {}

    /** Any filter change starts the utilisation table from its first page again. */
    public function updated(string $property): void
    {
        if (in_array($property, self::FILTER_PROPERTIES, true)) {
            $this->resetPage(self::UTILISATION_PAGE_NAME);
        }
    }

    /** @return array<ExportAction> */
    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make('exportRevenue')
                ->color('gray')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->label('Export settled card payments')
                ->exporter(PaymentExporter::class)
                ->formats([ExportFormat::Xlsx])
                ->modifyQueryUsing(fn ($query) => $this->reportQuery()->paymentExportQuery($this->manager(), $this->filters())),
            ExportAction::make('exportBookings')
                ->color('gray')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->label('Export bookings')
                ->exporter(BookingExporter::class)
                ->formats([ExportFormat::Xlsx])
                ->modifyQueryUsing(fn ($query) => $this->reportQuery()->bookingExportQuery($this->manager(), $this->filters())),
            ExportAction::make('exportFinance')
                ->color('gray')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->label('Export invoices')
                ->exporter(InvoiceExporter::class)
                ->formats([ExportFormat::Xlsx])
                ->modifyQueryUsing(fn ($query) => $this->reportQuery()->invoiceExportQuery($this->manager(), $this->filters())),
        ];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $manager = auth()->user();
        abort_unless($manager instanceof User, 403);

        $validator = $this->dateValidator();
        $datesAreInvalid = $validator->fails();
        $this->resetValidation(['startDate', 'endDate']);
        foreach ($validator->errors()->all() as $message) {
            $this->addError('startDate', $message);
        }

        $query = app(ManagementReportQuery::class);
        $centres = $query->authorisedCentres($manager);
        $facilities = Facility::query()->whereIn('centre_id', $centres->pluck('id')->all())
            ->when($this->centreId !== null, fn ($builder) => $builder->where('centre_id', $this->centreId))
            ->orderBy('name')->get(['id', 'centre_id', 'name']);
        $resources = Resource::query()->whereIn('facility_id', $facilities->modelKeys())
            ->when($this->facilityId !== null, fn ($builder) => $builder->where('facility_id', $this->facilityId))
            ->orderBy('name')->get(['id', 'facility_id', 'name']);
        $report = null;
        $utilisation = null;
        $utilisationTrend = [];
        $utilisationOverall = null;
        $utilisationTrendIsWeekly = false;

        if (! $datesAreInvalid) {
            $report = $query->summary($manager, $this->filters());
            $utilisation = $this->paginateUtilisation($report['utilisation']);
            $utilisationTrendIsWeekly = CarbonImmutable::parse($this->startDate)->diffInDays(CarbonImmutable::parse($this->endDate)) + 1 > self::DAILY_TREND_MAX_DAYS;
            $utilisationTrend = $this->utilisationTrend($report['utilisation'], $utilisationTrendIsWeekly);
            $utilisationOverall = $this->utilisationTotals($report['utilisation']);
        }

        return compact('centres', 'facilities', 'resources', 'report', 'utilisation', 'utilisationTrend', 'utilisationTrendIsWeekly', 'utilisationOverall');
    }

    /**
     * Only the current page of utilisation rows is sent to the browser.
     *
     * @param  list<array{date: string, centre: string, facility: string, resource: string, availableMinutes: int, bookedMinutes: int, utilisationPercent: float}>  $rows
     * @return LengthAwarePaginator<int, array{date: string, centre: string, facility: string, resource: string, availableMinutes: int, bookedMinutes: int, utilisationPercent: float}>
     */
    private function paginateUtilisation(array $rows): LengthAwarePaginator
    {
        $lastPage = max(1, (int) ceil(count($rows) / self::UTILISATION_PER_PAGE));
        $page = min(max(1, (int) $this->getPage(self::UTILISATION_PAGE_NAME)), $lastPage);

        return new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * self::UTILISATION_PER_PAGE, self::UTILISATION_PER_PAGE),
            count($rows),
            self::UTILISATION_PER_PAGE,
            $page,
            ['pageName' => self::UTILISATION_PAGE_NAME],
        );
    }

    /**
     * Presentation-only roll-up of the utilisation rows by day (or by week for long periods), using the same
     * booked ÷ available definition as each row.
     *
     * @param  list<array{date: string, centre: string, facility: string, resource: string, availableMinutes: int, bookedMinutes: int, utilisationPercent: float}>  $rows
     * @return list<array{label: string, bookedMinutes: int, availableMinutes: int, utilisationPercent: float}>
     */
    private function utilisationTrend(array $rows, bool $isWeekly): array
    {
        return array_values(collect($rows)
            ->groupBy(fn (array $row): string => $isWeekly ? CarbonImmutable::parse($row['date'])->startOfWeek(CarbonImmutable::MONDAY)->toDateString() : $row['date'])
            ->sortKeys()
            ->map(function ($group, string $date) use ($isWeekly): array {
                $totals = $this->utilisationTotals($group->all());

                return ['label' => ($isWeekly ? 'Week of ' : '').CarbonImmutable::parse($date)->format('D j M'), ...$totals];
            })
            ->all());
    }

    /**
     * @param  array<array{bookedMinutes: int, availableMinutes: int}>  $rows
     * @return array{bookedMinutes: int, availableMinutes: int, utilisationPercent: float}
     */
    private function utilisationTotals(array $rows): array
    {
        $booked = (int) array_sum(array_column($rows, 'bookedMinutes'));
        $available = (int) array_sum(array_column($rows, 'availableMinutes'));

        return ['bookedMinutes' => $booked, 'availableMinutes' => $available, 'utilisationPercent' => $available === 0 ? 0.0 : round(($booked / $available) * 100, 1)];
    }

    /**
     * Builds the active report filters. Exports call this directly, so invalid dates are rejected here too.
     *
     * @throws ValidationException
     */
    private function filters(): ReportFilters
    {
        $this->dateValidator()->validate();

        return ReportFilters::forLocalDates($this->startDate, $this->endDate, $this->centreId, $this->facilityId, $this->resourceId);
    }

    private function dateValidator(): ValidatorContract
    {
        return Validator::make(
            ['startDate' => $this->startDate, 'endDate' => $this->endDate],
            ReportFilters::dateRules(),
        )->after(function (ValidatorContract $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $days = CarbonImmutable::parse($this->startDate)->diffInDays(CarbonImmutable::parse($this->endDate)) + 1;
            if ($days > ReportFilters::MAX_RANGE_DAYS) {
                $validator->errors()->add('endDate', 'The reporting period may not exceed '.ReportFilters::MAX_RANGE_DAYS.' days.');
            }
        });
    }

    private function manager(): User
    {
        $manager = auth()->user();
        abort_unless($manager instanceof User, 403);

        return $manager;
    }

    private function reportQuery(): ManagementReportQuery
    {
        return app(ManagementReportQuery::class);
    }
}
