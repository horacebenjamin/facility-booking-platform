<x-filament-panels::page>
    <form wire:submit="submit" class="grid gap-6">
        @if ($successReference !== null)
            <x-filament::section heading="Booking request created">
                <p>Booking reference: <strong>{{ $successReference }}</strong>. The request is awaiting normal management approval and payment handling.</p>
            </x-filament::section>
        @endif

        @if ($errorMessage !== null)
            <x-filament::section heading="Booking could not be created">
                <p role="alert">{{ $errorMessage }}</p>
            </x-filament::section>
        @endif

        <x-filament::section heading="Customer and venue">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="customer-search">Find customer</label>
                    <x-filament::input.wrapper>
                        <x-filament::input id="customer-search" wire:model.live.debounce.300ms="customerSearch" placeholder="Name or email" />
                    </x-filament::input.wrapper>
                </div>
                <div>
                    <label for="assisted-customer">Customer</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="assisted-customer" wire:model.live="customerId">
                            <option value="">Select customer</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }} · {{ $customer->email }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    @error('customerId') <p role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="assisted-centre">Centre</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="assisted-centre" wire:model.live="centreId">
                            <option value="">Select centre</option>
                            @foreach ($centres as $centre)
                                <option value="{{ $centre->id }}">{{ $centre->name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    @error('centreId') <p role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="assisted-resource">Resource</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="assisted-resource" wire:model.live="resourceId" :disabled="$centreId === null">
                            <option value="">Select resource</option>
                            @foreach ($resources as $resource)
                                <option value="{{ $resource->id }}">{{ $resource->facility->name }} · {{ $resource->name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    @error('resourceId') <p role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="Date and time">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="assisted-starts-at">Starts</label>
                    <x-filament::input.wrapper>
                        <x-filament::input id="assisted-starts-at" type="datetime-local" wire:model.live="startsAt" />
                    </x-filament::input.wrapper>
                    @error('startsAt') <p role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="assisted-ends-at">Ends</label>
                    <x-filament::input.wrapper>
                        <x-filament::input id="assisted-ends-at" type="datetime-local" wire:model.live="endsAt" />
                    </x-filament::input.wrapper>
                    @error('endsAt') <p role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="Equipment and add-ons">
            <div class="grid gap-3">
                @forelse ($equipmentOptions as $equipment)
                    <label class="flex items-center gap-3">
                        <input type="checkbox" wire:model.live="selectedEquipmentIds" value="{{ $equipment->id }}" />
                        <span>{{ $equipment->name }} ({{ $equipment->quantity }} available)</span>
                        <x-filament::input type="number" min="1" max="{{ $equipment->quantity }}" wire:model.live="equipmentQuantities.{{ $equipment->id }}" :disabled="! in_array($equipment->id, array_map('intval', $selectedEquipmentIds), true)" aria-label="Quantity for {{ $equipment->name }}" />
                    </label>
                @empty
                    <p>No active equipment is configured for this centre.</p>
                @endforelse
            </div>
        </x-filament::section>

        <x-filament::section heading="Authorised price override">
            <p class="mb-4">Leave both fields empty to use the authoritative calculated price. An override is limited to authorised managers and is recorded in the booking audit trail.</p>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="override-amount">Adjusted total (minor units)</label>
                    <x-filament::input id="override-amount" type="number" min="0" wire:model.live="overrideAmountMinor" />
                    @error('overrideAmountMinor') <p role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="override-reason">Reason</label>
                    <x-filament::input id="override-reason" wire:model.live="overrideReason" />
                    @error('overrideReason') <p role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-filament::section>

        @if ($quote !== null)
            <x-filament::section heading="Authoritative price review">
                <p>Calculated total: <strong>{{ $quote['currency'] }} {{ number_format($quote['calculated_total_minor'] / 100, 2) }}</strong></p>
                <p>Booking total: <strong>{{ $quote['currency'] }} {{ number_format($quote['final_total_minor'] / 100, 2) }}</strong></p>
            </x-filament::section>
        @endif

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="button" wire:click="preview" wire:loading.attr="disabled">Check availability and price</x-filament::button>
            <x-filament::button type="submit" color="success" wire:loading.attr="disabled">Create booking request</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
