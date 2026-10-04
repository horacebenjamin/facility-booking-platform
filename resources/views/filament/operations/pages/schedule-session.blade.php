<li class="today-schedule-session" wire:key="{{ $sectionKey }}-{{ $session->bookingId }}">
    <h3>{{ $session->startsAt->format('H:i') }}–{{ $session->endsAt->format('H:i') }} · {{ $session->reference }}</h3>
    <p>{{ $session->facilityName }} · {{ $session->resourceName }}</p>
    <p>Confirmed · {{ $session->phase($currentTime) }}</p>
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
</li>
