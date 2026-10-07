<x-filament-panels::page>
    @php
        $money = fn (int $minor): string => '£'.number_format($minor / 100, 2);
        $percentOf = fn (int|float $part, int|float $whole): float => $whole > 0 ? round(($part / $whole) * 100, 1) : 0.0;
        $duration = function (int $minutes): string {
            $hours = intdiv($minutes, 60);
            $remainder = $minutes % 60;

            return match (true) {
                $hours === 0 => $remainder.'m',
                $remainder === 0 => number_format($hours).'h',
                default => number_format($hours).'h '.str_pad((string) $remainder, 2, '0', STR_PAD_LEFT).'m',
            };
        };
    @endphp

    <div class="space-y-6">

        {{-- Filters --}}
        <section class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-white/10">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300">
                        <x-filament::icon icon="heroicon-o-funnel" class="h-5 w-5" />
                    </div>

                    <div>
                        <h2 class="font-semibold text-gray-950 dark:text-white">Report filters</h2>

                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                            All dates are interpreted in Europe/London. Results are limited to your assigned centres.
                        </p>
                    </div>
                </div>

                <div wire:loading.delay class="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400" role="status">
                    <x-filament::loading-indicator class="h-4 w-4" />
                    Updating report…
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-2 lg:grid-cols-5">
                <label class="grid gap-1.5">
                    <span class="text-sm font-medium text-gray-950 dark:text-white">Start date</span>
                    <x-filament::input.wrapper :valid="! $errors->has('startDate')">
                        <x-filament::input type="date" wire:model.live="startDate" />
                    </x-filament::input.wrapper>
                </label>

                <label class="grid gap-1.5">
                    <span class="text-sm font-medium text-gray-950 dark:text-white">End date</span>
                    <x-filament::input.wrapper :valid="! $errors->has('startDate')">
                        <x-filament::input type="date" wire:model.live="endDate" />
                    </x-filament::input.wrapper>
                </label>

                <label class="grid gap-1.5">
                    <span class="text-sm font-medium text-gray-950 dark:text-white">Centre</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="centreId">
                            <option value="">All assigned centres</option>
                            @foreach ($centres as $centre)
                                <option value="{{ $centre->id }}">{{ $centre->name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                <label class="grid gap-1.5">
                    <span class="text-sm font-medium text-gray-950 dark:text-white">Facility</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="facilityId">
                            <option value="">All facilities</option>
                            @foreach ($facilities as $facility)
                                <option value="{{ $facility->id }}">{{ $facility->name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                <label class="grid gap-1.5">
                    <span class="text-sm font-medium text-gray-950 dark:text-white">Resource</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="resourceId">
                            <option value="">All resources</option>
                            @foreach ($resources as $resource)
                                <option value="{{ $resource->id }}">{{ $resource->name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
            </div>

            @error('startDate')
                <div class="px-5 pb-4">
                    <p class="flex items-center gap-2 rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-400" role="alert">
                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-4 w-4 shrink-0" />
                        {{ $message }}
                    </p>
                </div>
            @enderror
        </section>

        @if ($report !== null)
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3" wire:loading.class.delay="opacity-60">

                {{-- Revenue --}}
                <section class="flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                                <x-filament::icon icon="heroicon-o-banknotes" class="h-5 w-5" />
                            </div>

                            <div>
                                <h3 class="font-semibold text-gray-950 dark:text-white">Revenue</h3>

                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Collected revenue: successful card payments and paid invoice lines only, by settlement date.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="px-5 py-4">
                        <p class="text-3xl font-bold tracking-tight text-gray-950 dark:text-white">
                            {{ $money($report['revenue']['totalMinor']) }}
                        </p>

                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ number_format($report['revenue']['transactions']) }} settled payment line{{ $report['revenue']['transactions'] === 1 ? '' : 's' }}
                        </p>
                    </div>

                    @php
                        $revenueGroups = [
                            'byCentre' => 'Centre',
                            'byFacility' => 'Facility',
                            'byResource' => 'Resource',
                            'byCustomer' => 'Customer',
                            'byOrganisation' => 'Organisation',
                            'byPeriod' => 'Period',
                        ];
                        $revenueGroupHelp = [
                            'byCustomer' => 'Personal bookings only. Organisation bookings are attributed to the organisation.',
                            'byOrganisation' => 'Bookings owned by an organisation account.',
                            'byPeriod' => 'Grouped by the Europe/London settlement date.',
                        ];
                    @endphp

                    <div x-data="{ revenueTab: 'byCentre' }" class="flex flex-1 flex-col border-t border-gray-100 dark:border-white/5">
                        <x-filament::tabs contained label="Revenue breakdown" role="tablist">
                            @foreach ($revenueGroups as $group => $label)
                                <x-filament::tabs.item
                                    :alpine-active="'revenueTab === \'' . $group . '\''"
                                    x-on:click="revenueTab = '{{ $group }}'"
                                    role="tab"
                                    id="revenue-tab-{{ $group }}"
                                    aria-controls="revenue-panel-{{ $group }}"
                                    x-bind:aria-selected="(revenueTab === '{{ $group }}').toString()"
                                >
                                    {{ $label }}
                                </x-filament::tabs.item>
                            @endforeach
                        </x-filament::tabs>

                        @foreach ($revenueGroups as $group => $label)
                            <div
                                id="revenue-panel-{{ $group }}"
                                role="tabpanel"
                                aria-labelledby="revenue-tab-{{ $group }}"
                                x-show="revenueTab === '{{ $group }}'"
                                @if (! $loop->first) x-cloak @endif
                                class="px-5 py-4"
                            >
                                @if (isset($revenueGroupHelp[$group]))
                                    <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">{{ $revenueGroupHelp[$group] }}</p>
                                @endif

                                <ul class="max-h-72 space-y-3 overflow-y-auto pe-1">
                                    @forelse ($report['revenue'][$group] as $row)
                                        @php
                                            $share = $percentOf($row['amountMinor'], $report['revenue']['totalMinor']);
                                        @endphp
                                        <li>
                                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                                <span class="min-w-0 truncate text-gray-700 dark:text-gray-200" title="{{ $row['label'] }}">
                                                    @if ($group === 'byPeriod')
                                                        <time datetime="{{ $row['label'] }}">{{ \Carbon\CarbonImmutable::parse($row['label'])->format('D j M Y') }}</time>
                                                    @else
                                                        {{ $row['label'] }}
                                                    @endif
                                                </span>

                                                <span class="shrink-0 tabular-nums">
                                                    <span class="font-semibold text-gray-950 dark:text-white">{{ $money($row['amountMinor']) }}</span>
                                                    <span class="ms-1 text-xs text-gray-500 dark:text-gray-400">{{ number_format($share, 1) }}%</span>
                                                </span>
                                            </div>

                                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10" aria-hidden="true">
                                                <div class="h-full rounded-full bg-emerald-500 dark:bg-emerald-400" style="width: {{ min(100, $share) }}%"></div>
                                            </div>
                                        </li>
                                    @empty
                                        <li class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">No settled revenue</li>
                                    @endforelse
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- Bookings --}}
                @php
                    $bookingStatusTotal = $report['bookings']['confirmed'] + $report['bookings']['cancelled'] + $report['bookings']['rejected'];
                @endphp
                <section class="flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-600 dark:bg-orange-500/10 dark:text-orange-400">
                                <x-filament::icon icon="heroicon-o-calendar-days" class="h-5 w-5" />
                            </div>

                            <div>
                                <h3 class="font-semibold text-gray-950 dark:text-white">Bookings</h3>

                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Booking state is based on the booked start date; no-shows use the explicit attendance state.
                                </p>
                            </div>
                        </div>
                    </div>

                    <dl class="grid grid-cols-2 gap-3 px-5 py-4">
                        <div class="rounded-xl bg-success-50 p-4 ring-1 ring-success-600/10 dark:bg-success-500/10 dark:ring-success-400/20">
                            <dt class="flex items-center gap-1.5 text-sm font-medium text-success-700 dark:text-success-400">
                                <x-filament::icon icon="heroicon-m-check-circle" class="h-4 w-4" />
                                Confirmed
                            </dt>
                            <dd class="mt-1 text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ number_format($report['bookings']['confirmed']) }}</dd>
                        </div>

                        <div class="rounded-xl bg-danger-50 p-4 ring-1 ring-danger-600/10 dark:bg-danger-500/10 dark:ring-danger-400/20">
                            <dt class="flex items-center gap-1.5 text-sm font-medium text-danger-700 dark:text-danger-400">
                                <x-filament::icon icon="heroicon-m-x-circle" class="h-4 w-4" />
                                Cancelled
                            </dt>
                            <dd class="mt-1 text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ number_format($report['bookings']['cancelled']) }}</dd>
                        </div>

                        <div class="rounded-xl bg-orange-50 p-4 ring-1 ring-orange-600/10 dark:bg-orange-500/10 dark:ring-orange-400/20">
                            <dt class="flex items-center gap-1.5 text-sm font-medium text-orange-700 dark:text-orange-400">
                                <x-filament::icon icon="heroicon-m-no-symbol" class="h-4 w-4" />
                                Rejected
                            </dt>
                            <dd class="mt-1 text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ number_format($report['bookings']['rejected']) }}</dd>
                        </div>

                        <div class="rounded-xl bg-violet-50 p-4 ring-1 ring-violet-600/10 dark:bg-violet-500/10 dark:ring-violet-400/20">
                            <dt class="flex items-center gap-1.5 text-sm font-medium text-violet-700 dark:text-violet-400">
                                <x-filament::icon icon="heroicon-m-user-minus" class="h-4 w-4" />
                                No-show
                            </dt>
                            <dd class="mt-1 text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ number_format($report['bookings']['noShow']) }}</dd>
                        </div>
                    </dl>

                    <div class="mt-auto space-y-3 border-t border-gray-100 px-5 py-4 dark:border-white/5">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Booking status mix</p>

                            @if ($bookingStatusTotal > 0)
                                <div class="mt-2 flex h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10" role="img" aria-label="Confirmed {{ $percentOf($report['bookings']['confirmed'], $bookingStatusTotal) }}%, cancelled {{ $percentOf($report['bookings']['cancelled'], $bookingStatusTotal) }}%, rejected {{ $percentOf($report['bookings']['rejected'], $bookingStatusTotal) }}%">
                                    <div class="h-full bg-success-500" style="width: {{ $percentOf($report['bookings']['confirmed'], $bookingStatusTotal) }}%"></div>
                                    <div class="h-full bg-danger-500" style="width: {{ $percentOf($report['bookings']['cancelled'], $bookingStatusTotal) }}%"></div>
                                    <div class="h-full bg-orange-500" style="width: {{ $percentOf($report['bookings']['rejected'], $bookingStatusTotal) }}%"></div>
                                </div>

                                <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-300">
                                    <li class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-success-500"></span>Confirmed {{ number_format($percentOf($report['bookings']['confirmed'], $bookingStatusTotal), 1) }}%</li>
                                    <li class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-danger-500"></span>Cancelled {{ number_format($percentOf($report['bookings']['cancelled'], $bookingStatusTotal), 1) }}%</li>
                                    <li class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-orange-500"></span>Rejected {{ number_format($percentOf($report['bookings']['rejected'], $bookingStatusTotal), 1) }}%</li>
                                </ul>
                            @else
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No confirmed, cancelled or rejected bookings in this period.</p>
                            @endif
                        </div>

                        <p class="flex items-start gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-m-information-circle" class="mt-px h-4 w-4 shrink-0" />
                            No-show is an attendance outcome, so those bookings are also counted under their booking status.
                        </p>
                    </div>
                </section>

                {{-- Finance --}}
                <section class="flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 md:col-span-2 xl:col-span-1 dark:bg-gray-900 dark:ring-white/10">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                                <x-filament::icon icon="heroicon-o-document-text" class="h-5 w-5" />
                            </div>

                            <div>
                                <h3 class="font-semibold text-gray-950 dark:text-white">Finance</h3>

                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Paid: settled in the period. Outstanding: unpaid invoices issued by the period end. Overdue: outstanding invoices past their due date today.
                                </p>
                            </div>
                        </div>
                    </div>

                    <dl class="grid grid-cols-1 gap-3 px-5 py-4 sm:grid-cols-3 md:grid-cols-3 xl:grid-cols-1">
                        <div class="flex items-center justify-between gap-3 rounded-xl bg-success-50 p-4 ring-1 ring-success-600/10 sm:block xl:flex dark:bg-success-500/10 dark:ring-success-400/20">
                            <dt class="flex items-center gap-1.5 text-sm font-medium text-success-700 dark:text-success-400">
                                <x-filament::icon icon="heroicon-m-check-badge" class="h-4 w-4" />
                                Paid
                            </dt>
                            <dd class="text-end sm:mt-1 sm:text-start xl:mt-0 xl:text-end">
                                <span class="block text-xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $money($report['finance']['paidMinor']) }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($report['finance']['paidCount']) }} invoice{{ $report['finance']['paidCount'] === 1 ? '' : 's' }}</span>
                            </dd>
                        </div>

                        <div class="flex items-center justify-between gap-3 rounded-xl bg-warning-50 p-4 ring-1 ring-warning-600/10 sm:block xl:flex dark:bg-warning-500/10 dark:ring-warning-400/20">
                            <dt class="flex items-center gap-1.5 text-sm font-medium text-warning-700 dark:text-warning-400">
                                <x-filament::icon icon="heroicon-m-clock" class="h-4 w-4" />
                                Outstanding
                            </dt>
                            <dd class="text-end sm:mt-1 sm:text-start xl:mt-0 xl:text-end">
                                <span class="block text-xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $money($report['finance']['outstandingMinor']) }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($report['finance']['outstandingCount']) }} invoice{{ $report['finance']['outstandingCount'] === 1 ? '' : 's' }}</span>
                            </dd>
                        </div>

                        <div class="flex items-center justify-between gap-3 rounded-xl bg-danger-50 p-4 ring-1 ring-danger-600/10 sm:block xl:flex dark:bg-danger-500/10 dark:ring-danger-400/20">
                            <dt class="flex items-center gap-1.5 text-sm font-medium text-danger-700 dark:text-danger-400">
                                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-4 w-4" />
                                Overdue
                            </dt>
                            <dd class="text-end sm:mt-1 sm:text-start xl:mt-0 xl:text-end">
                                <span class="block text-xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $money($report['finance']['overdueMinor']) }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($report['finance']['overdueCount']) }} invoice{{ $report['finance']['overdueCount'] === 1 ? '' : 's' }}</span>
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-auto border-t border-gray-100 px-5 py-4 dark:border-white/5">
                        @php
                            $overdueShare = $percentOf($report['finance']['overdueMinor'], $report['finance']['outstandingMinor']);
                        @endphp
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Overdue share of outstanding</p>
                            <p class="text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($overdueShare, 1) }}%</p>
                        </div>

                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-warning-100 dark:bg-warning-500/20" aria-hidden="true">
                            <div class="h-full rounded-full bg-danger-500" style="width: {{ min(100, $overdueShare) }}%"></div>
                        </div>

                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Overdue invoices are included in the outstanding total.
                        </p>
                    </div>
                </section>
            </div>

            {{-- Utilisation --}}
            <section
                x-data="{ utilisationView: 'chart' }"
                class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                wire:loading.class.delay="opacity-60"
            >
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-white/10">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400">
                            <x-filament::icon icon="heroicon-o-chart-bar" class="h-5 w-5" />
                        </div>

                        <div>
                            <h3 class="font-semibold text-gray-950 dark:text-white">Utilisation</h3>

                            <p class="mt-0.5 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                                Confirmed operational occupancy (including setup and cleanup) divided by configured facility/resource bookable time after active closures.
                            </p>
                        </div>
                    </div>

                    @if ($utilisation->total() > 0)
                        <x-filament::tabs label="Utilisation view" role="tablist" class="!mx-0 !p-1">
                            <x-filament::tabs.item
                                alpine-active="utilisationView === 'chart'"
                                x-on:click="utilisationView = 'chart'"
                                icon="heroicon-m-chart-bar"
                                role="tab"
                                id="utilisation-tab-chart"
                                aria-controls="utilisation-panel-chart"
                                x-bind:aria-selected="(utilisationView === 'chart').toString()"
                            >
                                Chart
                            </x-filament::tabs.item>

                            <x-filament::tabs.item
                                alpine-active="utilisationView === 'table'"
                                x-on:click="utilisationView = 'table'"
                                icon="heroicon-m-table-cells"
                                role="tab"
                                id="utilisation-tab-table"
                                aria-controls="utilisation-panel-table"
                                x-bind:aria-selected="(utilisationView === 'table').toString()"
                            >
                                Table
                            </x-filament::tabs.item>
                        </x-filament::tabs>
                    @endif
                </div>

                @if ($utilisation->total() === 0)
                    <div class="flex min-h-32 flex-col items-center justify-center px-6 py-10 text-center">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-400 dark:bg-white/5">
                            <x-filament::icon icon="heroicon-o-clock" class="h-6 w-6" />
                        </div>

                        <p class="mt-3 font-medium text-gray-700 dark:text-gray-200">
                            No configured bookable resource time for this period.
                        </p>

                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Check the date range, filters and bookable hours for the selected facilities and resources.
                        </p>
                    </div>
                @else
                    {{-- Summary --}}
                    <dl class="grid grid-cols-1 gap-3 border-b border-gray-100 px-5 py-4 sm:grid-cols-3 dark:border-white/5">
                        <div class="rounded-xl bg-sky-50 p-4 ring-1 ring-sky-600/10 dark:bg-sky-500/10 dark:ring-sky-400/20">
                            <dt class="text-sm font-medium text-sky-700 dark:text-sky-400">Overall utilisation</dt>
                            <dd class="mt-1 text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ number_format($utilisationOverall['utilisationPercent'], 1) }}%</dd>
                        </div>

                        <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-300">Booked time</dt>
                            <dd class="mt-1 text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $duration($utilisationOverall['bookedMinutes']) }}</dd>
                        </div>

                        <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                            <dt class="text-sm font-medium text-gray-600 dark:text-gray-300">Available time</dt>
                            <dd class="mt-1 text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $duration($utilisationOverall['availableMinutes']) }}</dd>
                        </div>
                    </dl>

                    {{-- Chart view --}}
                    <div
                        id="utilisation-panel-chart"
                        role="tabpanel"
                        aria-labelledby="utilisation-tab-chart"
                        x-show="utilisationView === 'chart'"
                        class="px-5 py-5"
                    >
                        <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                            {{ $utilisationTrendIsWeekly ? 'Weekly' : 'Daily' }} utilisation across the selected scope. Switch to the table for each facility and resource.
                        </p>

                        <div class="flex gap-3">
                            <div class="flex h-48 flex-col justify-between text-end text-[0.6875rem] tabular-nums text-gray-400 dark:text-gray-500" aria-hidden="true">
                                <span>100%</span>
                                <span>50%</span>
                                <span>0%</span>
                            </div>

                            <div class="min-w-0 flex-1 overflow-x-auto">
                                <div class="relative h-48 min-w-full border-b border-l border-gray-200 dark:border-white/10">
                                    <div class="pointer-events-none absolute inset-x-0 top-1/2 border-t border-dashed border-gray-200 dark:border-white/10" aria-hidden="true"></div>

                                    <ol class="relative flex h-full items-end gap-1 px-1" aria-label="Utilisation by {{ $utilisationTrendIsWeekly ? 'week' : 'day' }}">
                                    @foreach ($utilisationTrend as $point)
                                        <li class="group relative flex h-full min-w-2 flex-1 items-end" title="{{ $point['label'] }}: {{ number_format($point['utilisationPercent'], 1) }}% ({{ $duration($point['bookedMinutes']) }} of {{ $duration($point['availableMinutes']) }})">
                                            <span class="sr-only">{{ $point['label'] }}: {{ number_format($point['utilisationPercent'], 1) }}% utilised, {{ $duration($point['bookedMinutes']) }} booked of {{ $duration($point['availableMinutes']) }} available</span>
                                            <div class="w-full rounded-t bg-sky-500 transition group-hover:bg-sky-600 dark:bg-sky-400 dark:group-hover:bg-sky-300" style="height: {{ max($point['utilisationPercent'] > 0 ? 1 : 0, min(100, $point['utilisationPercent'])) }}%" aria-hidden="true"></div>
                                        </li>
                                    @endforeach
                                    </ol>
                                </div>

                                @if (count($utilisationTrend) > 0)
                                    <div class="mt-1.5 flex justify-between text-[0.6875rem] text-gray-400 dark:text-gray-500" aria-hidden="true">
                                        <span>{{ $utilisationTrend[0]['label'] }}</span>
                                        <span>{{ $utilisationTrend[array_key_last($utilisationTrend)]['label'] }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Table view --}}
                    <div
                        id="utilisation-panel-table"
                        role="tabpanel"
                        aria-labelledby="utilisation-tab-table"
                        x-show="utilisationView === 'table'"
                        x-cloak
                    >
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[44rem] text-start text-sm">
                                <thead class="bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                    <tr>
                                        <th scope="col" class="px-5 py-3 text-start">Date</th>
                                        <th scope="col" class="px-3 py-3 text-start">Centre</th>
                                        <th scope="col" class="px-3 py-3 text-start">Facility / resource</th>
                                        <th scope="col" class="px-3 py-3 text-end">Booked</th>
                                        <th scope="col" class="px-3 py-3 text-end">Available</th>
                                        <th scope="col" class="w-56 px-5 py-3 text-start">Utilisation</th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                    @foreach ($utilisation as $row)
                                        <tr class="transition hover:bg-gray-50 dark:hover:bg-white/5" wire:key="utilisation-{{ $utilisation->currentPage() }}-{{ $loop->index }}">
                                            <td class="whitespace-nowrap px-5 py-3 text-gray-700 dark:text-gray-200">
                                                <time datetime="{{ $row['date'] }}">{{ \Carbon\CarbonImmutable::parse($row['date'])->format('D j M Y') }}</time>
                                            </td>
                                            <td class="px-3 py-3 text-gray-700 dark:text-gray-200">{{ $row['centre'] }}</td>
                                            <td class="px-3 py-3">
                                                <span class="font-medium text-gray-950 dark:text-white">{{ $row['facility'] }}</span>
                                                <span class="text-gray-500 dark:text-gray-400">/ {{ $row['resource'] }}</span>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-3 text-end tabular-nums text-gray-700 dark:text-gray-200">{{ $duration($row['bookedMinutes']) }}</td>
                                            <td class="whitespace-nowrap px-3 py-3 text-end tabular-nums text-gray-500 dark:text-gray-400">{{ $duration($row['availableMinutes']) }}</td>
                                            <td class="px-5 py-3">
                                                <div class="flex items-center gap-3">
                                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10" aria-hidden="true">
                                                        <div class="h-full rounded-full bg-sky-500 dark:bg-sky-400" style="width: {{ min(100, $row['utilisationPercent']) }}%"></div>
                                                    </div>
                                                    <span class="w-14 shrink-0 text-end font-medium tabular-nums text-gray-950 dark:text-white">{{ number_format($row['utilisationPercent'], 1) }}%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="border-t border-gray-100 px-5 py-3 dark:border-white/5">
                            <x-filament::pagination :paginator="$utilisation" />
                        </div>
                    </div>
                @endif
            </section>
        @endif
    </div>
</x-filament-panels::page>
