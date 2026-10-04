<x-filament-panels::page>
    <style>
        .today-schedule { display: grid; gap: 1.5rem; }
        .today-schedule-controls, .today-schedule-priorities { display: grid; gap: 1rem; }
        .today-schedule-field { display: grid; gap: .5rem; }
        .today-schedule-list { display: grid; gap: 1rem; list-style: none; padding: 0; }
        .today-schedule-session { display: grid; gap: .5rem; border: 1px solid #9ca3af; border-radius: .75rem; padding: 1rem; overflow-wrap: anywhere; }
        .today-schedule-session h3 { font-weight: 600; }
        .today-schedule-equipment { list-style: disc; padding-inline-start: 1.25rem; }
        .today-schedule-meta { font-size: .875rem; }
        @media (min-width: 48rem) {
            .today-schedule-controls { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto; align-items: end; }
            .today-schedule-priorities { grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; }
        }
    </style>

    <div class="today-schedule" wire:poll.30s>
        @if ($centres->isEmpty())
            <x-filament::section heading="No assigned centres">
                <p>You do not have an assigned centre. Ask your manager to assign the centre you are covering.</p>
            </x-filament::section>
        @else
            <div class="today-schedule-controls">
                <div class="today-schedule-field">
                    <label for="schedule-centre">Centre</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="schedule-centre" wire:model.live="centreId">
                            @foreach ($centres as $centre)
                                <option value="{{ $centre->id }}" @selected($selectedCentre?->id === $centre->id)>{{ $centre->name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
                <div class="today-schedule-field">
                    <label for="schedule-date">Date</label>
                    <x-filament::input.wrapper>
                        <x-filament::input id="schedule-date" type="date" wire:model.live="date" aria-describedby="schedule-date-error" aria-invalid="{{ $errors->has('date') ? 'true' : 'false' }}" />
                    </x-filament::input.wrapper>
                    @error('date') <p id="schedule-date-error" role="alert">{{ $message }}</p> @enderror
                </div>
                <x-filament::button wire:click="refreshSchedule" wire:loading.attr="disabled">Refresh schedule</x-filament::button>
            </div>

            @if ($schedule !== null)
                <p class="today-schedule-meta">{{ $selectedCentre->name }} · {{ $schedule->date }} · {{ $timezone }}<br>Last refreshed {{ $schedule->refreshedAt->format('H:i:s') }}. Updates automatically every 30 seconds.</p>

                <div class="today-schedule-priorities">
                    <x-filament::section heading="Now">
                        <ul class="today-schedule-list">
                            @forelse ($schedule->now as $session)
                                @include('filament.operations.pages.schedule-session', ['session' => $session, 'currentTime' => $schedule->refreshedAt, 'sectionKey' => 'now'])
                            @empty
                                <li>No sessions are using resources now for the selected date.</li>
                            @endforelse
                        </ul>
                    </x-filament::section>
                    <x-filament::section heading="Next">
                        <ul class="today-schedule-list">
                            @forelse ($schedule->next as $session)
                                @include('filament.operations.pages.schedule-session', ['session' => $session, 'currentTime' => $schedule->refreshedAt, 'sectionKey' => 'next'])
                            @empty
                                <li>No upcoming sessions for the selected date.</li>
                            @endforelse
                        </ul>
                    </x-filament::section>
                </div>

                <x-filament::section heading="Full-day schedule">
                    <ul class="today-schedule-list">
                        @forelse ($schedule->sessions as $session)
                            @include('filament.operations.pages.schedule-session', ['session' => $session, 'currentTime' => $schedule->refreshedAt, 'sectionKey' => 'day'])
                        @empty
                            <li>No scheduled bookings for {{ $selectedCentre->name }} on {{ $schedule->date }}.</li>
                        @endforelse
                    </ul>
                </x-filament::section>
            @endif
        @endif
    </div>
</x-filament-panels::page>
