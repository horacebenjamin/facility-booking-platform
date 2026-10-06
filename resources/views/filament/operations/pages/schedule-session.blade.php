<li class="today-schedule-session" wire:key="{{ $sectionKey }}-{{ $session->bookingId }}">
    <h3>{{ $session->startsAt->format('H:i') }}–{{ $session->endsAt->format('H:i') }} · {{ $session->reference }}</h3>
    <p>{{ $session->facilityName }} · {{ $session->resourceName }}</p>
    <p>Confirmed · {{ $session->phase($currentTime) }}</p>
    <p>Attendance: {{ $session->attendanceState->label() }}</p>
    @if ($session->arrivedAt !== null)
        <p>Arrived {{ $session->arrivedAt->format('d M H:i') }}</p>
    @endif
    @if ($session->noShowRecordedAt !== null)
        <p>No-show recorded {{ $session->noShowRecordedAt->format('d M H:i') }}</p>
    @endif
    @if ($session->completedAt !== null)
        <p>Completed {{ $session->completedAt->format('d M H:i') }}</p>
    @endif
    <p>{{ $session->customerName }}</p>
    @if ($session->operationalStartsAt->lessThan($session->startsAt))
        <p>Setup starts {{ $session->operationalStartsAt->format('d M H:i') }}</p>
    @endif
    <p>Booking {{ $session->startsAt->format('d M H:i') }}–{{ $session->endsAt->format('d M H:i') }}</p>
    @if ($session->operationalEndsAt->greaterThan($session->endsAt))
        <p>Cleanup complete {{ $session->operationalEndsAt->format('d M H:i') }}</p>
    @endif
    @if ($session->equipment !== [])
        <p>Equipment required</p>
        <ul class="today-schedule-equipment">
            @foreach ($session->equipment as $equipment)
                <li>{{ $equipment['quantity'] }} × {{ $equipment['name'] }}</li>
            @endforeach
        </ul>
    @else
        <p>No equipment required.</p>
    @endif
    @if ($session->seriesIdentifier !== null)
        <p class="today-schedule-meta">Series {{ $session->seriesIdentifier }} · Occurrence {{ $session->occurrenceIndex }} of {{ $session->occurrenceCount }}</p>
    @endif
    @if ($session->availableTransitions !== [])
        <div class="today-schedule-actions" role="group" aria-label="Attendance actions for {{ $session->reference }}">
            @if (in_array(\App\Enums\AttendanceState::Arrived, $session->availableTransitions, true))
                <x-filament::button wire:click="mountAction('recordArrival', { booking: {{ $session->bookingId }} })">Booking arrived</x-filament::button>
            @endif
            @if (in_array(\App\Enums\AttendanceState::NoShow, $session->availableTransitions, true))
                <x-filament::button color="warning" wire:click="mountAction('recordNoShow', { booking: {{ $session->bookingId }} })">Mark as no-show</x-filament::button>
            @endif
            @if (in_array(\App\Enums\AttendanceState::Completed, $session->availableTransitions, true))
                <x-filament::button wire:click="mountAction('completeBooking', { booking: {{ $session->bookingId }} })">Complete booking</x-filament::button>
            @endif
        </div>
    @endif
    @if (auth()->user()?->can('incidents.manage'))
        <div class="today-schedule-actions" role="group" aria-label="Operational issue actions for {{ $session->reference }}">
            <x-filament::button color="gray" wire:click="mountAction('reportIncident', { booking: {{ $session->bookingId }} })">Report incident</x-filament::button>
            <x-filament::button color="gray" wire:click="mountAction('reportDamage', { booking: {{ $session->bookingId }} })">Report damage</x-filament::button>
        </div>
    @endif
</li>
