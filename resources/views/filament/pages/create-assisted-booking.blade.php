<x-filament-panels::page>
    @php
        $money = fn (int $minor, string $currency = 'GBP'): string => ($currency === 'GBP' ? '£' : $currency.' ').number_format($minor / 100, 2);
        $duration = function (int $minutes): string {
            $hours = intdiv($minutes, 60);
            $remainder = $minutes % 60;

            return match (true) {
                $hours === 0 => $remainder.'m',
                $remainder === 0 => $hours.'h',
                default => $hours.'h '.str_pad((string) $remainder, 2, '0', STR_PAD_LEFT).'m',
            };
        };
        $cardClass = 'overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10';
        $labelClass = 'text-sm font-medium text-gray-950 dark:text-white';
        $hintClass = 'text-sm text-gray-500 dark:text-gray-400';
        $errorClass = 'text-sm text-danger-600 dark:text-danger-400';
        $hasOverride = $overrideAmountMinor !== null;
        $localRange = fn (\Carbon\CarbonInterface $starts, \Carbon\CarbonInterface $ends): string => $starts->format('D j M Y, H:i').'–'.($ends->isSameDay($starts) ? $ends->format('H:i') : $ends->format('D j M Y, H:i'));
        $customerSearchTerm = trim($customerSearch);
    @endphp

    <form wire:submit="submit" class="grid grid-cols-1 gap-6 xl:grid-cols-12 xl:items-start">

        {{-- Created confirmation --}}
        @if ($successReference !== null)
            <section class="{{ $cardClass }} xl:col-span-12" role="status">
                <div class="flex flex-wrap items-start justify-between gap-4 px-5 py-4">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400">
                            <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5" />
                        </div>

                        <div>
                            <h2 class="font-semibold text-gray-950 dark:text-white">Booking request created</h2>

                            <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">
                                Booking reference: <strong class="font-semibold text-gray-950 dark:text-white">{{ $successReference }}</strong>. The request is awaiting normal management approval and payment handling.
                            </p>

                            @if ($successBooking !== null)
                                <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-filament::icon icon="heroicon-o-calendar-days" class="h-4 w-4" />
                                        <span data-test="assisted-confirmation-time">{{ $localRange($successBooking->starts_at->timezone($localTimezone), $successBooking->ends_at->timezone($localTimezone)) }} (UK time)</span>
                                    </span>

                                    <span class="inline-flex items-center gap-1.5">
                                        <x-filament::icon icon="{{ $successBooking->organisation !== null ? 'heroicon-o-building-office' : 'heroicon-o-user' }}" class="h-4 w-4" />
                                        {{ $successBooking->organisation !== null ? 'Organisation: '.$successBooking->organisation->name : 'Personal booking' }}
                                    </span>
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if ($successBookingId !== null)
                            <x-filament::button
                                tag="a"
                                href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('view', ['record' => $successBookingId], panel: 'management') }}"
                                color="gray"
                                size="sm"
                                icon="heroicon-o-arrow-top-right-on-square"
                                outlined
                            >
                                View booking
                            </x-filament::button>
                        @endif

                        <x-filament::button type="button" wire:click="startNewBooking" size="sm" icon="heroicon-o-plus">
                            Start new booking
                        </x-filament::button>
                    </div>
                </div>
            </section>
        @endif

        {{-- Booking details --}}
        <div class="space-y-6 xl:col-span-8">

            {{-- Customer and venue --}}
            <section class="{{ $cardClass }}" aria-labelledby="assisted-customer-heading">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                                <x-filament::icon icon="heroicon-o-user-circle" class="h-5 w-5" />
                            </div>

                            <div>
                                <h2 id="assisted-customer-heading" class="font-semibold text-gray-950 dark:text-white">Customer and venue</h2>

                                <p class="mt-0.5 {{ $hintClass }}">Who the booking is for, and where it takes place.</p>
                            </div>
                        </div>

                        {{ $this->createCustomerAction }}
                    </div>
                </div>

                <div class="space-y-5 px-5 py-5">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid content-start gap-1.5">
                            <label for="customer-search" class="{{ $labelClass }}">Find customer</label>

                            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                                <x-filament::input
                                    id="customer-search"
                                    type="search"
                                    wire:model.live.debounce.300ms="customerSearch"
                                    placeholder="Name, email or phone"
                                    autocomplete="off"
                                />
                            </x-filament::input.wrapper>
                        </div>

                        <div class="grid content-start gap-1.5">
                            <label for="assisted-customer" class="{{ $labelClass }}">Customer</label>

                            <x-filament::input.wrapper :valid="! $errors->has('customerId')">
                                <x-filament::input.select id="assisted-customer" wire:model.live="customerId">
                                    <option value="">{{ $customers->isEmpty() ? 'No matching customers' : 'Select customer' }}</option>
                                    @foreach ($customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->name }} · {{ $customer->email }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>

                            @error('customerId') <p class="{{ $errorClass }}" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    @if ($selectedCustomer !== null)
                        <div class="flex items-center gap-3 rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700 dark:bg-primary-500/20 dark:text-primary-300" aria-hidden="true">
                                {{ \Illuminate\Support\Str::of($selectedCustomer->name)->explode(' ')->filter()->take(2)->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-gray-950 dark:text-white">{{ $selectedCustomer->name }}</p>

                                <p class="flex flex-wrap gap-x-4 gap-y-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    <span class="inline-flex min-w-0 items-center gap-1.5">
                                        <x-filament::icon icon="heroicon-o-envelope" class="h-4 w-4 shrink-0" />
                                        <span class="truncate">{{ $selectedCustomer->email }}</span>
                                    </span>

                                    @if ($selectedCustomer->phone)
                                        <span class="inline-flex items-center gap-1.5">
                                            <x-filament::icon icon="heroicon-o-phone" class="h-4 w-4 shrink-0" />
                                            {{ $selectedCustomer->phone }}
                                        </span>
                                    @endif
                                </p>
                            </div>

                            <x-filament::badge color="success" icon="heroicon-m-check">Selected</x-filament::badge>
                        </div>

                        @if ($organisationOptions->isNotEmpty())
                            <div class="grid gap-1.5 md:max-w-md">
                                <label for="assisted-organisation" class="{{ $labelClass }}">Book for organisation</label>

                                <x-filament::input.wrapper prefix-icon="heroicon-m-building-office" :valid="! $errors->has('organisationId')">
                                    <x-filament::input.select id="assisted-organisation" wire:model.live="organisationId" aria-describedby="assisted-organisation-hint">
                                        <option value="">Personal booking</option>
                                        @foreach ($organisationOptions as $organisation)
                                            <option value="{{ $organisation->id }}">{{ $organisation->name }}</option>
                                        @endforeach
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>

                                <p id="assisted-organisation-hint" class="{{ $hintClass }}">Optional. Only organisations this customer is allowed to book for are listed.</p>

                                @error('organisationId') <p class="{{ $errorClass }}" role="alert">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        @if ($organisationOptions->isEmpty())
                            @error('organisationId') <p class="{{ $errorClass }}" role="alert">{{ $message }}</p> @enderror
                        @endif
                    @elseif ($customerSearchTerm !== '' && $customers->isEmpty())
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dashed border-gray-300 px-4 py-3 dark:border-white/15">
                            <div class="flex items-start gap-3">
                                <x-filament::icon icon="heroicon-o-user-plus" class="mt-0.5 h-5 w-5 shrink-0 text-gray-400" />

                                <div>
                                    <p class="font-medium text-gray-700 dark:text-gray-200">No customer matches “{{ $customerSearchTerm }}”.</p>

                                    <p class="{{ $hintClass }}">If they are new, use <span class="font-medium text-gray-700 dark:text-gray-200">Create new customer</span>. What you've typed will be pre-filled.</p>
                                </div>
                            </div>
                        </div>
                    @else
                        <p class="{{ $hintClass }}">
                            Search by name, email or phone. Customer not on the system yet? Use <span class="font-medium text-gray-700 dark:text-gray-200">Create new customer</span>.
                        </p>
                    @endif

                    <div class="border-t border-gray-100 pt-5 dark:border-white/5">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid content-start gap-1.5">
                                <label for="assisted-centre" class="{{ $labelClass }}">Centre</label>

                                <x-filament::input.wrapper prefix-icon="heroicon-m-map-pin" :valid="! $errors->has('centreId')">
                                    <x-filament::input.select id="assisted-centre" wire:model.live="centreId">
                                        <option value="">Select centre</option>
                                        @foreach ($centres as $centre)
                                            <option value="{{ $centre->id }}">{{ $centre->name }}</option>
                                        @endforeach
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>

                                @error('centreId') <p class="{{ $errorClass }}" role="alert">{{ $message }}</p> @enderror
                            </div>

                            <div class="grid content-start gap-1.5">
                                <label for="assisted-resource" class="{{ $labelClass }}">Resource</label>

                                <x-filament::input.wrapper prefix-icon="heroicon-m-building-office-2" :disabled="$centreId === null" :valid="! $errors->has('resourceId')">
                                    <x-filament::input.select id="assisted-resource" wire:model.live="resourceId" :disabled="$centreId === null">
                                        <option value="">{{ $centreId === null ? 'Select a centre first' : 'Select resource' }}</option>
                                        @foreach ($resources as $resource)
                                            <option value="{{ $resource->id }}">{{ $resource->facility->name }} · {{ $resource->name }}</option>
                                        @endforeach
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>

                                @error('resourceId') <p class="{{ $errorClass }}" role="alert">{{ $message }}</p> @enderror

                                @if ($centreId !== null && $resources->isEmpty())
                                    <p class="{{ $hintClass }}">No bookable resources are configured for this centre.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Date and time --}}
            <section class="{{ $cardClass }}" aria-labelledby="assisted-time-heading">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-warning-400">
                                <x-filament::icon icon="heroicon-o-clock" class="h-5 w-5" />
                            </div>

                            <div>
                                <h2 id="assisted-time-heading" class="font-semibold text-gray-950 dark:text-white">Date and time</h2>

                                <p class="mt-0.5 {{ $hintClass }}">Enter local UK time. Setup and cleanup are applied automatically.</p>
                            </div>
                        </div>

                        @if ($timeRange !== null)
                            <x-filament::badge color="warning" icon="heroicon-m-clock">{{ $duration($timeRange['minutes']) }}</x-filament::badge>
                        @endif
                    </div>
                </div>

                <div class="grid gap-4 px-5 py-5 md:grid-cols-2">
                    <div class="grid content-start gap-1.5">
                        <label for="assisted-starts-at" class="{{ $labelClass }}">Starts</label>

                        <x-filament::input.wrapper :valid="! $errors->has('startsAt')">
                            <x-filament::input id="assisted-starts-at" type="datetime-local" wire:model.live="startsAt" />
                        </x-filament::input.wrapper>

                        @error('startsAt') <p class="{{ $errorClass }}" role="alert">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid content-start gap-1.5">
                        <label for="assisted-ends-at" class="{{ $labelClass }}">Ends</label>

                        <x-filament::input.wrapper :valid="! $errors->has('endsAt')">
                            <x-filament::input id="assisted-ends-at" type="datetime-local" wire:model.live="endsAt" />
                        </x-filament::input.wrapper>

                        @error('endsAt') <p class="{{ $errorClass }}" role="alert">{{ $message }}</p> @enderror
                    </div>

                    @if ($timeRange === null && $startsAt !== '' && $endsAt !== '')
                        <p class="flex items-center gap-2 text-sm text-warning-700 md:col-span-2 dark:text-warning-400">
                            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-4 w-4 shrink-0" />
                            The end time must be after the start time.
                        </p>
                    @endif
                </div>
            </section>

            {{-- Equipment and add-ons --}}
            <section class="{{ $cardClass }}" aria-labelledby="assisted-equipment-heading">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300">
                                <x-filament::icon icon="heroicon-o-cube" class="h-5 w-5" />
                            </div>

                            <div>
                                <h2 id="assisted-equipment-heading" class="font-semibold text-gray-950 dark:text-white">Equipment and add-ons</h2>

                                <p class="mt-0.5 {{ $hintClass }}">Optional. Availability for the selected time is confirmed when you check the price.</p>
                            </div>
                        </div>

                        <span class="inline-flex min-w-8 items-center justify-center rounded-full bg-gray-100 px-2.5 py-1 text-sm font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">
                            {{ count($selectedEquipmentIdList) }} selected
                        </span>
                    </div>
                </div>

                <div class="px-5 py-5">
                    @if ($centreId === null)
                        <p class="{{ $hintClass }}">Select a centre to see its equipment.</p>
                    @else
                        <ul class="grid gap-3" role="list">
                            @forelse ($equipmentOptions as $equipment)
                                @php($isSelected = in_array($equipment->id, $selectedEquipmentIdList, true))

                                <li wire:key="equipment-{{ $equipment->id }}" @class([
                                    'flex flex-wrap items-center gap-x-4 gap-y-3 rounded-xl px-4 py-3 ring-1 transition',
                                    'bg-primary-50/60 ring-primary-600/30 dark:bg-primary-500/10 dark:ring-primary-400/30' => $isSelected,
                                    'ring-gray-950/10 dark:ring-white/10' => ! $isSelected,
                                ])>
                                    <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-3">
                                        <x-filament::input.checkbox wire:model.live="selectedEquipmentIds" value="{{ $equipment->id }}" />

                                        <span class="min-w-0">
                                            <span class="block truncate font-medium text-gray-950 dark:text-white">{{ $equipment->name }}</span>
                                            <span class="block text-sm text-gray-500 dark:text-gray-400">{{ $equipment->quantity }} in stock</span>
                                        </span>
                                    </label>

                                    <div class="flex items-center gap-2">
                                        <span class="text-sm text-gray-500 dark:text-gray-400" aria-hidden="true">Qty</span>

                                        <x-filament::input.wrapper :disabled="! $isSelected" :valid="! $errors->has('equipmentQuantities.'.$equipment->id)" class="w-24">
                                            <x-filament::input
                                                type="number"
                                                min="1"
                                                max="{{ $equipment->quantity }}"
                                                wire:model.live.debounce.400ms="equipmentQuantities.{{ $equipment->id }}"
                                                :disabled="! $isSelected"
                                                aria-label="Quantity for {{ $equipment->name }}"
                                            />
                                        </x-filament::input.wrapper>
                                    </div>

                                    @error('equipmentQuantities.'.$equipment->id) <p class="w-full {{ $errorClass }}" role="alert">{{ $message }}</p> @enderror
                                </li>
                            @empty
                                <li class="flex flex-col items-center justify-center px-6 py-6 text-center">
                                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-400 dark:bg-white/5">
                                        <x-filament::icon icon="heroicon-o-cube-transparent" class="h-6 w-6" />
                                    </div>

                                    <p class="mt-3 font-medium text-gray-700 dark:text-gray-200">No active equipment is configured for this centre.</p>
                                </li>
                            @endforelse
                        </ul>
                    @endif
                </div>
            </section>

            {{-- Authorised booking details --}}
            <section class="{{ $cardClass }}" aria-labelledby="assisted-override-heading">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300">
                                <x-filament::icon icon="heroicon-o-adjustments-horizontal" class="h-5 w-5" />
                            </div>

                            <div>
                                <h2 id="assisted-override-heading" class="font-semibold text-gray-950 dark:text-white">Authorised booking details</h2>

                                <p class="mt-0.5 {{ $hintClass }}">Optional price override and internal note, both recorded in the audit trail.</p>
                            </div>
                        </div>

                        <x-filament::badge :color="$hasOverride ? 'warning' : 'gray'">
                            {{ $hasOverride ? 'Override entered' : 'Not applied' }}
                        </x-filament::badge>
                    </div>
                </div>

                <div class="space-y-4 px-5 py-5">
                    <div>
                        <h3 class="{{ $labelClass }}">Authorised price override</h3>

                        <p class="{{ $hintClass }}">Leave both fields empty to use the authoritative calculated price.</p>
                    </div>

                    <p class="flex items-start gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-300">
                        <x-filament::icon icon="heroicon-o-information-circle" class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                        An override is limited to authorised managers and is recorded in the booking audit trail with your name and reason.
                    </p>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid content-start gap-1.5">
                            <label for="override-amount" class="{{ $labelClass }}">Adjusted total (pence)</label>

                            <x-filament::input.wrapper suffix="pence" :valid="! $errors->has('overrideAmountMinor')">
                                <x-filament::input id="override-amount" type="number" min="0" step="1" wire:model.live.debounce.400ms="overrideAmountMinor" placeholder="e.g. 6000" aria-describedby="override-amount-hint" />
                            </x-filament::input.wrapper>

                            <p id="override-amount-hint" class="{{ $hintClass }}">
                                @if ($hasOverride && is_numeric($overrideAmountMinor))
                                    Booking total will be <span class="font-medium text-gray-950 dark:text-white">{{ $money((int) $overrideAmountMinor) }}</span>.
                                @else
                                    Enter the full booking total in pence, e.g. 6000 = £60.00.
                                @endif
                            </p>

                            @error('overrideAmountMinor') <p class="{{ $errorClass }}" role="alert">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid content-start gap-1.5">
                            <label for="override-reason" class="{{ $labelClass }}">Reason</label>

                            <x-filament::input.wrapper :valid="! $errors->has('overrideReason')">
                                <x-filament::input id="override-reason" wire:model.live.debounce.400ms="overrideReason" placeholder="e.g. Community partnership rate" />
                            </x-filament::input.wrapper>

                            @error('overrideReason') <p class="{{ $errorClass }}" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-1.5 border-t border-gray-100 pt-5 dark:border-white/5">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <label for="assisted-staff-note" class="{{ $labelClass }}">Internal staff note</label>

                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ mb_strlen($staffNote) }}/{{ \App\Actions\CreateManualBooking::STAFF_NOTE_MAX_LENGTH }}</span>
                        </div>

                        <textarea
                            id="assisted-staff-note"
                            wire:model.live.debounce.500ms="staffNote"
                            rows="3"
                            maxlength="{{ \App\Actions\CreateManualBooking::STAFF_NOTE_MAX_LENGTH }}"
                            placeholder="e.g. Caller requested wheelchair access"
                            aria-describedby="assisted-staff-note-hint"
                            @class([
                                'block w-full rounded-lg border-none bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 transition placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:placeholder:text-gray-500 dark:focus:ring-primary-500',
                                'ring-gray-950/10 dark:ring-white/20' => ! $errors->has('staffNote'),
                                'ring-danger-600 dark:ring-danger-500' => $errors->has('staffNote'),
                            ])
                        ></textarea>

                        <p id="assisted-staff-note-hint" class="flex items-center gap-1.5 {{ $hintClass }}">
                            <x-filament::icon icon="heroicon-o-lock-closed" class="h-4 w-4 shrink-0" />
                            Visible to staff only. Never shown to the customer.
                        </p>

                        @error('staffNote') <p class="{{ $errorClass }}" role="alert">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>
        </div>

        {{-- Summary and actions --}}
        <aside class="space-y-6 xl:sticky xl:top-24 xl:col-span-4" aria-labelledby="assisted-summary-heading">
            <section class="{{ $cardClass }}">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-info-50 text-info-600 dark:bg-info-500/10 dark:text-info-400">
                                <x-filament::icon icon="heroicon-o-clipboard-document-check" class="h-5 w-5" />
                            </div>

                            <div>
                                <h2 id="assisted-summary-heading" class="font-semibold text-gray-950 dark:text-white">Booking summary</h2>

                                <p class="mt-0.5 {{ $hintClass }}">Review before creating the request.</p>
                            </div>
                        </div>

                        <div wire:loading.delay class="text-gray-400" role="status">
                            <x-filament::loading-indicator class="h-5 w-5" />
                            <span class="sr-only">Updating…</span>
                        </div>
                    </div>
                </div>

                <dl class="divide-y divide-gray-100 text-sm dark:divide-white/5">
                    <div class="flex gap-3 px-5 py-3">
                        <dt class="flex w-24 shrink-0 items-center gap-1.5 text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-o-user" class="h-4 w-4" /> Customer
                        </dt>
                        <dd class="min-w-0 text-gray-950 dark:text-white">
                            @if ($selectedCustomer !== null)
                                <span class="block truncate font-medium">{{ $selectedCustomer->name }}</span>
                                <span class="block truncate text-gray-500 dark:text-gray-400">{{ $selectedCustomer->email }}</span>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">Not selected</span>
                            @endif
                        </dd>
                    </div>

                    <div class="flex gap-3 px-5 py-3">
                        <dt class="flex w-24 shrink-0 items-center gap-1.5 text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-o-building-office" class="h-4 w-4" /> Owner
                        </dt>
                        <dd class="min-w-0 text-gray-950 dark:text-white">
                            @if ($selectedOrganisation !== null)
                                <span class="block truncate font-medium">Organisation: {{ $selectedOrganisation->name }}</span>
                            @else
                                <span class="block">Personal booking</span>
                            @endif
                        </dd>
                    </div>

                    <div class="flex gap-3 px-5 py-3">
                        <dt class="flex w-24 shrink-0 items-center gap-1.5 text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-o-map-pin" class="h-4 w-4" /> Venue
                        </dt>
                        <dd class="min-w-0 text-gray-950 dark:text-white">
                            @if ($selectedResource !== null)
                                <span class="block font-medium">{{ $selectedResource->facility->name }} / {{ $selectedResource->name }}</span>
                            @else
                                <span class="block text-gray-400 dark:text-gray-500">No resource selected</span>
                            @endif

                            @if ($selectedCentre !== null)
                                <span class="block text-gray-500 dark:text-gray-400">{{ $selectedCentre->name }}</span>
                            @endif
                        </dd>
                    </div>

                    <div class="flex gap-3 px-5 py-3">
                        <dt class="flex w-24 shrink-0 items-center gap-1.5 text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-o-calendar-days" class="h-4 w-4" /> When
                        </dt>
                        <dd class="min-w-0 text-gray-950 dark:text-white">
                            @if ($timeRange !== null)
                                <span class="block font-medium">{{ $timeRange['starts']->format('D j M Y') }}</span>
                                <span class="block text-gray-500 dark:text-gray-400">
                                    {{ $timeRange['starts']->format('H:i') }}–{{ $timeRange['ends']->isSameDay($timeRange['starts']) ? $timeRange['ends']->format('H:i') : $timeRange['ends']->format('j M, H:i') }}
                                    · {{ $duration($timeRange['minutes']) }}
                                </span>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">Enter a valid time range</span>
                            @endif
                        </dd>
                    </div>

                    <div class="flex gap-3 px-5 py-3">
                        <dt class="flex w-24 shrink-0 items-center gap-1.5 text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-o-cube" class="h-4 w-4" /> Equipment
                        </dt>
                        <dd class="min-w-0 text-gray-950 dark:text-white">
                            @forelse ($selectedEquipment as $equipment)
                                <span class="block truncate">{{ (int) ($equipmentQuantities[$equipment->id] ?? 1) }} × {{ $equipment->name }}</span>
                            @empty
                                <span class="text-gray-400 dark:text-gray-500">None</span>
                            @endforelse
                        </dd>
                    </div>

                    <div class="flex gap-3 px-5 py-3">
                        <dt class="flex w-24 shrink-0 items-center gap-1.5 text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-o-adjustments-horizontal" class="h-4 w-4" /> Override
                        </dt>
                        <dd class="min-w-0 text-gray-950 dark:text-white">
                            @if ($hasOverride && is_numeric($overrideAmountMinor))
                                <span class="block font-medium">{{ $money((int) $overrideAmountMinor) }}</span>
                                @if (trim($overrideReason) !== '')
                                    <span class="block truncate text-gray-500 dark:text-gray-400">{{ $overrideReason }}</span>
                                @endif
                            @else
                                <span class="text-gray-400 dark:text-gray-500">None</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                {{-- Availability and price --}}
                <div class="border-t border-gray-200 px-5 py-4 dark:border-white/10">
                    @if ($errorMessage !== null)
                        <div class="rounded-xl bg-danger-50 px-4 py-3 ring-1 ring-danger-600/20 dark:bg-danger-500/10 dark:ring-danger-400/30" role="alert">
                            <p class="flex items-center gap-2 font-semibold text-danger-700 dark:text-danger-400">
                                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 shrink-0" />
                                This booking cannot go ahead yet
                            </p>
                            <p class="mt-1 text-sm text-danger-700 dark:text-danger-300">{{ $errorMessage }}</p>
                        </div>
                    @elseif ($quote !== null)
                        <div class="rounded-xl bg-success-50 px-4 py-3 ring-1 ring-success-600/20 dark:bg-success-500/10 dark:ring-success-400/30">
                            <p class="flex items-center gap-2 font-semibold text-success-700 dark:text-success-400">
                                <x-filament::icon icon="heroicon-o-check-badge" class="h-5 w-5 shrink-0" />
                                Available · authoritative price review
                            </p>

                            <dl class="mt-3 space-y-1 text-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-gray-600 dark:text-gray-300">Calculated total</dt>
                                    <dd @class([
                                        'font-medium text-gray-950 dark:text-white',
                                        'line-through decoration-gray-400' => $quote['calculated_total_minor'] !== $quote['final_total_minor'],
                                    ])>{{ $money((int) $quote['calculated_total_minor'], (string) $quote['currency']) }}</dd>
                                </div>

                                <div class="flex items-center justify-between gap-3 border-t border-success-600/10 pt-2 dark:border-success-400/20">
                                    <dt class="font-medium text-gray-950 dark:text-white">Booking total</dt>
                                    <dd class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $money((int) $quote['final_total_minor'], (string) $quote['currency']) }}</dd>
                                </div>
                            </dl>
                        </div>
                    @else
                        <div class="flex items-start gap-3 rounded-xl border border-dashed border-gray-300 px-4 py-3 dark:border-white/15">
                            <x-filament::icon icon="heroicon-o-magnifying-glass-circle" class="mt-0.5 h-5 w-5 shrink-0 text-gray-400" />

                            <p class="{{ $hintClass }}">
                                Not checked yet. Check availability to confirm the slot is free and get the authoritative price.
                            </p>
                        </div>
                    @endif
                </div>

                <div class="grid gap-3 border-t border-gray-200 px-5 py-4 dark:border-white/10">
                    <x-filament::button
                        type="button"
                        wire:click="preview"
                        wire:target="preview"
                        wire:loading.attr="disabled"
                        color="gray"
                        icon="heroicon-o-magnifying-glass"
                        outlined
                        class="w-full"
                    >
                        Check availability and price
                    </x-filament::button>

                    <x-filament::button
                        type="submit"
                        wire:target="submit"
                        wire:loading.attr="disabled"
                        color="success"
                        icon="heroicon-o-check"
                        class="w-full"
                    >
                        Create booking request
                    </x-filament::button>

                    <p class="text-center text-xs text-gray-500 dark:text-gray-400">
                        Availability and price are re-checked when the request is created. The request then follows normal approval and payment handling.
                    </p>
                </div>
            </section>
        </aside>
    </form>
</x-filament-panels::page>
