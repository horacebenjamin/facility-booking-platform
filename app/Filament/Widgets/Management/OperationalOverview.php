<?php

namespace App\Filament\Widgets\Management;

use App\Models\User;
use App\Services\ManagementDashboardQuery;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Widget;

class OperationalOverview extends Widget implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.widgets.management.operational-overview';

    protected int|string|array $columnSpan = 'full';

    /** @var array<string, mixed>|null */
    protected ?array $cachedDashboard = null;

    public static function canView(): bool
    {
        return auth()->user() instanceof User && auth()->user()->hasRole('manager');
    }

    public function content(Schema $schema): Schema
    {
        $dashboard = $this->getDashboard();

        return $schema->components([
            Section::make()
                ->schema([
                    Stat::make(
                        'Pending requests',
                        $dashboard['pendingBookings']['count'],
                    )
                        ->icon('heroicon-o-inbox')
                        ->color(
                            $dashboard['pendingBookings']['count'] > 0
                                ? 'warning'
                                : 'gray'
                        ),

                    Stat::make(
                        'Awaiting payment',
                        $dashboard['awaitingPayment']['count'],
                    )
                        ->icon('heroicon-o-credit-card')
                        ->color(
                            $dashboard['awaitingPayment']['count'] > 0
                                ? 'warning'
                                : 'gray'
                        ),

                    Stat::make(
                        'Overdue invoices',
                        $dashboard['overdueInvoices']['count'],
                    )
                        ->icon('heroicon-o-document-currency-pound')
                        ->color(
                            $dashboard['overdueInvoices']['count'] > 0
                                ? 'danger'
                                : 'gray'
                        ),

                    Stat::make(
                        'Today’s bookings',
                        $dashboard['todaysBookings']['count'],
                    )
                        ->icon('heroicon-o-calendar-days')
                        ->color('primary'),

                    Stat::make(
                        'Operational issues',
                        $dashboard['operationalIssues']['count'],
                    )
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->color(
                            $dashboard['operationalIssues']['count'] > 0
                                ? 'warning'
                                : 'gray'
                        ),

                    Stat::make(
                        'Closure impacts',
                        $dashboard['closureImpacts']['count'],
                    )
                        ->icon('heroicon-o-no-symbol')
                        ->color(
                            $dashboard['closureImpacts']['count'] > 0
                                ? 'warning'
                                : 'gray'
                        ),

                    Stat::make(
                        'Open incidents',
                        $dashboard['openIncidents']['count'],
                    )
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color(
                            $dashboard['openIncidents']['count'] > 0
                                ? 'danger'
                                : 'gray'
                        ),
                ])
                ->columns([
                    'default' => 2,
                    'md' => 4,
                    'xl' => 7,
                ])
                ->contained(false)
                ->gridContainer(),
        ]);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $manager = auth()->user();

        abort_unless($manager instanceof User, 403);

        return ['dashboard' => $this->getDashboard()];
    }

    /** @return array<string, mixed> */
    private function getDashboard(): array
    {
        if ($this->cachedDashboard !== null) {
            return $this->cachedDashboard;
        }

        $manager = auth()->user();

        abort_unless($manager instanceof User, 403);

        return $this->cachedDashboard = app(ManagementDashboardQuery::class)->forManager($manager);
    }
}
