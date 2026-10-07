<x-filament-widgets::widget>
    @php
        $displayTimezone = 'Europe/London';

        $bookingsUrl = \App\Filament\Resources\Bookings\BookingResource::getUrl(
            panel: 'management'
        );

        $invoicesUrl = \App\Filament\Resources\Invoices\InvoiceResource::getUrl(
            panel: 'management'
        );

        $incidentsUrl = \App\Filament\Resources\Incidents\IncidentResource::getUrl(
            panel: 'management'
        );

        $damageReportsUrl = \App\Filament\Resources\DamageReports\DamageReportResource::getUrl(
            panel: 'management'
        );

        $closuresUrl = \App\Filament\Resources\AvailabilityBlocks\AvailabilityBlockResource::getUrl(
            panel: 'management'
        );

    @endphp

    <div class="space-y-6">

        {{-- Header --}}
        <div>
            <h2 class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                Management overview
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Work requiring attention across your assigned centres, plus today’s confirmed bookings.
            </p>
        </div>

        {{-- Summary statistics --}}
        <div class="[&_.fi-wi-stats-overview-stat]:text-center [&_.fi-wi-stats-overview-stat-content]:justify-items-center [&_.fi-wi-stats-overview-stat-label-ctn]:flex-row [&_.fi-wi-stats-overview-stat-label-ctn]:items-center [&_.fi-wi-stats-overview-stat-label-ctn]:justify-center [&_.fi-wi-stats-overview-stat-label-ctn]:gap-2 [&_.fi-wi-stats-overview-stat-label-ctn]:text-center [&_.fi-wi-stats-overview-stat-label-ctn_.fi-icon]:box-content [&_.fi-wi-stats-overview-stat-label-ctn_.fi-icon]:rounded-xl [&_.fi-wi-stats-overview-stat-label-ctn_.fi-icon]:bg-primary-100 [&_.fi-wi-stats-overview-stat-label-ctn_.fi-icon]:p-2 [&_.fi-wi-stats-overview-stat-label-ctn_.fi-icon]:!text-primary-600 dark:[&_.fi-wi-stats-overview-stat-label-ctn_.fi-icon]:bg-primary-500/20 dark:[&_.fi-wi-stats-overview-stat-label-ctn_.fi-icon]:!text-primary-400">
            {{ $this->content }}
        </div>

        {{-- Main dashboard --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">

            {{-- Pending requests --}}
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 lg:col-span-7">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                                <x-filament::icon
                                    icon="heroicon-o-inbox"
                                    class="h-5 w-5"
                                />
                            </div>

                            <div>
                                <h3 class="font-semibold text-gray-950 dark:text-white">
                                    Pending booking requests
                                </h3>

                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    Requests awaiting management review.
                                </p>
                            </div>
                        </div>

                        <span class="inline-flex min-w-8 items-center justify-center rounded-full bg-gray-100 px-2.5 py-1 text-sm font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">
                            {{ $dashboard['pendingBookings']['count'] }}
                        </span>
                    </div>
                </div>

                @if ($dashboard['pendingBookings']['count'] > 5)
                    <div class="border-b border-gray-100 bg-gray-50/60 px-5 py-2.5 text-xs font-medium text-gray-500 dark:border-white/5 dark:bg-white/[0.02] dark:text-gray-400">
                        Showing 5 of {{ $dashboard['pendingBookings']['count'] }} requests.
                    </div>
                @endif

                <div class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($dashboard['pendingBookings']['records'] as $booking)
                        <a
                            href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('view', ['record' => $booking], panel: 'management') }}"
                            class="group block px-5 py-4 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-600 dark:hover:bg-white/5"
                        >
                            <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <span class="font-semibold text-gray-950 dark:text-white">
                                            {{ $booking->reference }}
                                        </span>

                                        <span class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $booking->organisation?->name ?? $booking->customer->name }}
                                        </span>
                                    </div>

                                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                                        <span class="inline-flex items-center gap-1.5">
                                            <x-filament::icon
                                                icon="heroicon-o-calendar-days"
                                                class="h-4 w-4"
                                            />

                                            {{ $booking->starts_at->timezone($displayTimezone)->format('j M Y, H:i') }}
                                        </span>

                                        <span class="inline-flex items-center gap-1.5">
                                            <x-filament::icon
                                                icon="heroicon-o-map-pin"
                                                class="h-4 w-4"
                                            />

                                            {{ $booking->centre->name }}
                                        </span>
                                    </div>

                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                        {{ $booking->facility->name }} / {{ $booking->resource->name }}
                                    </p>
                                </div>

                                <x-filament::icon
                                    icon="heroicon-o-chevron-right"
                                    class="hidden h-5 w-5 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-gray-600 sm:block dark:group-hover:text-gray-200"
                                />
                            </div>
                        </a>
                    @empty
                        <div class="flex min-h-32 flex-col items-center justify-center px-6 py-8 text-center">
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-400 dark:bg-white/5">
                                <x-filament::icon
                                    icon="heroicon-o-check-circle"
                                    class="h-6 w-6"
                                />
                            </div>

                            <p class="mt-3 font-medium text-gray-700 dark:text-gray-200">
                                No pending booking requests.
                            </p>

                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                There is nothing waiting for management review.
                            </p>
                        </div>
                    @endforelse
                </div>

                @if ($dashboard['pendingBookings']['count'] > 0)
                    <div class="border-t border-gray-100 px-5 py-3 dark:border-white/5">
                        <x-filament::button
                            tag="a"
                            href="{{ $bookingsUrl }}"
                            color="gray"
                            size="sm"
                            icon="heroicon-o-arrow-right"
                            icon-position="after"
                            outlined
                        >
                            Review all requests
                        </x-filament::button>
                    </div>
                @endif
            </section>

            {{-- Right hand column --}}
            <div class="space-y-6 lg:col-span-5">

                {{-- Today's bookings --}}
                <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                                    <x-filament::icon
                                        icon="heroicon-o-calendar-days"
                                        class="h-5 w-5"
                                    />
                                </div>

                                <div>
                                    <h3 class="font-semibold text-gray-950 dark:text-white">
                                        Today’s bookings
                                    </h3>

                                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                        Confirmed bookings starting today.
                                    </p>
                                </div>
                            </div>

                            <span class="inline-flex min-w-8 items-center justify-center rounded-full bg-gray-100 px-2.5 py-1 text-sm font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                {{ $dashboard['todaysBookings']['count'] }}
                            </span>
                        </div>
                    </div>

                    <div class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($dashboard['todaysBookings']['records'] as $booking)
                            <a
                                href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('view', ['record' => $booking], panel: 'management') }}"
                                class="group block px-5 py-4 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-600 dark:hover:bg-white/5"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-950 dark:text-white">
                                            {{ $booking->reference }}
                                        </p>

                                        <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">
                                            {{ $booking->organisation?->name ?? $booking->customer->name }}
                                        </p>

                                        <x-filament::badge color="primary" class="mt-2">
                                            {{ $booking->starts_at->timezone($displayTimezone)->format('H:i') }}–{{ $booking->ends_at->timezone($displayTimezone)->format('H:i') }}
                                        </x-filament::badge>

                                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                            {{ $booking->centre->name }}
                                        </p>

                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $booking->facility->name }} / {{ $booking->resource->name }}
                                        </p>
                                    </div>

                                    <x-filament::icon
                                        icon="heroicon-o-chevron-right"
                                        class="mt-1 h-5 w-5 shrink-0 text-gray-400"
                                    />
                                </div>
                            </a>
                        @empty
                            <div class="flex min-h-32 flex-col items-center justify-center px-6 py-8 text-center">
                                <p class="font-medium text-gray-700 dark:text-gray-200">
                                    No confirmed bookings are scheduled to start today.
                                </p>

                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Nothing is currently scheduled to start today.
                                </p>
                            </div>
                        @endforelse
                    </div>
                </section>

                {{-- Payment / Invoice cards --}}
                <div class="space-y-6">

                    {{-- Awaiting payment --}}
                    <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-warning-400">
                                        <x-filament::icon
                                            icon="heroicon-o-credit-card"
                                            class="h-5 w-5"
                                        />
                                    </div>

                                    <div>
                                        <h3 class="font-semibold text-gray-950 dark:text-white">
                                            Awaiting payment
                                        </h3>

                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Approved bookings with payment currently due.
                                        </p>
                                    </div>
                                </div>

                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                    {{ $dashboard['awaitingPayment']['count'] }}
                                </span>
                            </div>
                        </div>

                        @forelse ($dashboard['awaitingPayment']['records'] as $booking)
                            <a
                                href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('view', ['record' => $booking], panel: 'management') }}"
                                class="group block px-5 py-4 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-600 dark:hover:bg-white/5"
                            >
                                <p class="font-semibold text-gray-950 dark:text-white">
                                    {{ $booking->reference }}
                                </p>

                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $booking->organisation?->name ?? $booking->customer->name }}
                                </p>

                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    @if ($booking->priceSnapshot)
                                        {{ $booking->priceSnapshot->currency }} {{ number_format($booking->priceSnapshot->final_total_minor / 100, 2) }} due
                                    @else
                                        Payment amount not recorded
                                    @endif
                                </p>
                            </a>
                        @empty
                            <div class="flex min-h-32 flex-col items-center justify-center px-6 py-8 text-center">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                    <x-filament::icon
                                        icon="heroicon-o-credit-card"
                                        class="h-5 w-5"
                                    />
                                </div>

                                <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-200">
                                    No approved bookings are awaiting payment.
                                </p>

                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    You’re all up to date.
                                </p>
                            </div>
                        @endforelse
                    </section>

                    {{-- Overdue invoices --}}
                    <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-warning-400">
                                        <x-filament::icon
                                            icon="heroicon-o-document-currency-pound"
                                            class="h-5 w-5"
                                        />
                                    </div>

                                    <div>
                                        <h3 class="font-semibold text-gray-950 dark:text-white">
                                            Overdue invoices
                                        </h3>

                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Issued invoices past their due date.
                                        </p>
                                    </div>
                                </div>

                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                    {{ $dashboard['overdueInvoices']['count'] }}
                                </span>
                            </div>
                        </div>

                        @forelse ($dashboard['overdueInvoices']['records'] as $invoice)
                            <a
                                href="{{ \App\Filament\Resources\Invoices\InvoiceResource::getUrl('view', ['record' => $invoice], panel: 'management') }}"
                                class="group block px-5 py-4 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-600 dark:hover:bg-white/5"
                            >
                                <p class="font-semibold text-gray-950 dark:text-white">
                                    {{ $invoice->reference }}
                                </p>

                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $invoice->organisation?->name ?? $invoice->customer->name }}
                                </p>

                                <div class="mt-2">
                                    <x-filament::badge color="danger">
                                        Due {{ $invoice->due_date->format('j M Y') }}
                                    </x-filament::badge>
                                </div>

                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    Outstanding {{ $invoice->currency }}
                                    {{ number_format($invoice->total_minor / 100, 2) }}
                                </p>
                            </a>
                        @empty
                            <div class="flex min-h-32 flex-col items-center justify-center px-6 py-8 text-center">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                    <x-filament::icon
                                        icon="heroicon-o-document-currency-pound"
                                        class="h-5 w-5"
                                    />
                                </div>

                                <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-200">
                                    No overdue invoices.
                                </p>

                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    You’re all up to date.
                                </p>
                            </div>
                        @endforelse

                        @if ($dashboard['overdueInvoices']['count'] > 0)
                            <div class="border-t border-gray-100 px-5 py-3 dark:border-white/5">
                                <a
                                    href="{{ $invoicesUrl }}"
                                    class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
                                >
                                    View invoice register →
                                </a>
                            </div>
                        @endif
                    </section>
                </div>

            </div>
        </div>

        {{-- Closure impacts --}}
        <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-warning-400">
                            <x-filament::icon
                                icon="heroicon-o-no-symbol"
                                class="h-5 w-5"
                            />
                        </div>

                        <div>
                            <h3 class="font-semibold text-gray-950 dark:text-white">
                                Closure affected bookings
                            </h3>

                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Unresolved impacts recorded against closures.
                            </p>
                        </div>
                    </div>

                    <span class="inline-flex min-w-8 items-center justify-center rounded-full bg-gray-100 px-2.5 py-1 text-sm font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">
                        {{ $dashboard['closureImpacts']['count'] }}
                    </span>
                </div>
            </div>

            @forelse ($dashboard['closureImpacts']['records'] as $impact)
                <a
                    href="{{ \App\Filament\Resources\AvailabilityBlocks\AvailabilityBlockResource::getUrl('view', ['record' => $impact->availabilityBlock], panel: 'management') }}"
                    class="group block px-5 py-4 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-600 dark:hover:bg-white/5"
                >
                    <p class="font-semibold text-gray-950 dark:text-white">
                        {{ $impact->booking->reference }}
                    </p>

                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        {{ $impact->booking->organisation?->name ?? $impact->booking->customer->name }}
                    </p>

                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ $impact->booking->centre->name }} ·
                        {{ $impact->booking->resource->name }}
                    </p>

                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $impact->availabilityBlock->type->label() }} ·
                        {{ $impact->availabilityBlock->reason }}
                    </p>
                </a>
            @empty
                <div class="flex min-h-32 flex-col items-center justify-center px-6 py-8 text-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <x-filament::icon
                            icon="heroicon-o-no-symbol"
                            class="h-5 w-5"
                        />
                    </div>

                    <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-200">
                        No unresolved closure impacts.
                    </p>

                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        No closure-related bookings currently require attention.
                    </p>
                </div>
            @endforelse

            @if ($dashboard['closureImpacts']['count'] > 0)
                <div class="border-t border-gray-100 px-5 py-3 dark:border-white/5">
                    <a
                        href="{{ $closuresUrl }}"
                        class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
                    >
                        View all closures →
                    </a>
                </div>
            @endif
        </section>

        {{-- Operational bottom row --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

            {{-- Damage --}}
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-warning-400">
                                <x-filament::icon
                                    icon="heroicon-o-wrench-screwdriver"
                                    class="h-5 w-5"
                                />
                            </div>

                            <div>
                                <h3 class="font-semibold text-gray-950 dark:text-white">
                                    Operational issues
                                </h3>

                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Damage reports still open or under review.
                                </p>
                            </div>
                        </div>

                        <span class="rounded-full bg-danger-50 px-2.5 py-1 text-sm font-semibold text-danger-600 dark:bg-danger-500/10 dark:text-danger-400">
                            {{ $dashboard['operationalIssues']['count'] }}
                        </span>
                    </div>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($dashboard['operationalIssues']['records'] as $report)
                        <a
                            href="{{ \App\Filament\Resources\DamageReports\DamageReportResource::getUrl('view', ['record' => $report], panel: 'management') }}"
                            class="group block px-5 py-4 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-600 dark:hover:bg-white/5"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-950 dark:text-white">
                                        Damage report
                                        @if ($report->booking)
                                            · {{ $report->booking->reference }}
                                        @endif
                                    </p>

                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $report->centre->name }} ·
                                        {{ $report->resource?->name ?? 'Resource not specified' }}
                                    </p>

                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $report->observed_at->timezone($displayTimezone)->format('j M Y, H:i') }}
                                    </p>
                                </div>

                                <x-filament::badge color="gray">
                                    {{ $report->status->label() }}
                                </x-filament::badge>
                            </div>
                        </a>
                    @empty
                        <div class="flex min-h-32 flex-col items-center justify-center px-6 py-8 text-center">
                            <p class="font-medium text-gray-700 dark:text-gray-200">
                                No unresolved operational issues.
                            </p>
                        </div>
                    @endforelse
                </div>

                @if ($dashboard['operationalIssues']['count'] > 0)
                    <div class="border-t border-gray-100 px-5 py-3 dark:border-white/5">
                        <a
                            href="{{ $damageReportsUrl }}"
                            class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
                        >
                            View all operational issues →
                        </a>
                    </div>
                @endif
            </section>

            {{-- Incidents --}}
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-danger-50 text-danger-600 dark:bg-danger-500/10 dark:text-danger-400">
                                <x-filament::icon
                                    icon="heroicon-o-exclamation-triangle"
                                    class="h-5 w-5"
                                />
                            </div>

                            <div>
                                <h3 class="font-semibold text-gray-950 dark:text-white">
                                    Open incidents
                                </h3>

                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Incidents that are open or reviewed and still need resolution.
                                </p>
                            </div>
                        </div>

                        <span class="inline-flex min-w-8 items-center justify-center rounded-full bg-danger-50 px-2.5 py-1 text-sm font-semibold text-danger-600 dark:bg-danger-500/10 dark:text-danger-400">
                            {{ $dashboard['openIncidents']['count'] }}
                        </span>
                    </div>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($dashboard['openIncidents']['records'] as $incident)
                        <a
                            href="{{ \App\Filament\Resources\Incidents\IncidentResource::getUrl('view', ['record' => $incident], panel: 'management') }}"
                            class="group block px-5 py-4 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-600 dark:hover:bg-white/5"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-950 dark:text-white">
                                        {{ $incident->title }}
                                    </p>

                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $incident->centre->name }} ·
                                        {{ $incident->booking?->reference ?? $incident->resource?->name ?? 'Centre incident' }}
                                    </p>

                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $incident->occurred_at->timezone($displayTimezone)->format('j M Y, H:i') }}
                                    </p>
                                </div>

                                <x-filament::badge color="gray">
                                    {{ $incident->status->label() }}
                                </x-filament::badge>
                            </div>
                        </a>
                    @empty
                        <div class="flex min-h-32 flex-col items-center justify-center px-6 py-8 text-center">
                            <p class="font-medium text-gray-700 dark:text-gray-200">
                                No open incidents.
                            </p>
                        </div>
                    @endforelse
                </div>

                @if ($dashboard['openIncidents']['count'] > 0)
                    <div class="border-t border-gray-100 px-5 py-3 dark:border-white/5">
                        <a
                            href="{{ $incidentsUrl }}"
                            class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
                        >
                            View all incidents →
                        </a>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-filament-widgets::widget>
