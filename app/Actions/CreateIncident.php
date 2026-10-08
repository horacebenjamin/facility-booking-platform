<?php

namespace App\Actions;

use App\Enums\OperationalIssueStatus;
use App\Models\Booking;
use App\Models\Centre;
use App\Models\Facility;
use App\Models\Incident;
use App\Models\Resource;
use App\Models\User;
use App\Services\CentreReservationLock;
use App\Services\ClosureAuthorization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateIncident
{
    public function __construct(private ClosureAuthorization $authorization, private CentreReservationLock $reservationLock) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data): Incident
    {
        $actor = $this->authorization->freshActor($actor);
        $data = Validator::make($data, [
            'centre_id' => ['required', 'integer', 'exists:centres,id'],
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
            'resource_id' => ['nullable', 'integer', 'exists:resources,id'],
            'issue_type' => ['required', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'immediate_action' => ['nullable', 'string', 'max:5000'],
            'occurred_at' => ['required', 'date'],
        ])->validate();
        $centre = Centre::query()->findOrFail((int) $data['centre_id']);
        Gate::forUser($actor)->authorize('create', [Incident::class, $centre]);

        return DB::transaction(function () use ($actor, $data, $centre): Incident {
            $centre = $this->reservationLock->lock($centre->id);
            $actor = $this->authorization->freshActor($actor);
            Gate::forUser($actor)->authorize('create', [Incident::class, $centre]);
            [$booking, $resource] = $this->validateContext($data, $centre);
            $incident = new Incident;
            $incident->forceFill([
                'centre_id' => $centre->id,
                'booking_id' => $booking?->id,
                'resource_id' => $resource?->id,
                'reported_by' => $actor->id,
                'issue_type' => trim($data['issue_type']),
                'title' => trim($data['title']),
                'description' => trim($data['description']),
                'immediate_action' => isset($data['immediate_action']) ? trim($data['immediate_action']) : null,
                'occurred_at' => CarbonImmutable::parse($data['occurred_at'], config('app.timezone')),
                'status' => OperationalIssueStatus::Open,
            ])->save();
            activity('incident')->performedOn($incident)->causedBy($actor)->event('incident.created')
                ->withProperties(['centre_id' => $centre->id, 'booking_id' => $booking?->id, 'resource_id' => $resource?->id, 'occurred_at' => $incident->occurred_at->toIso8601String()])
                ->log('Operational incident recorded');

            return $incident;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Booking|null, 1: resource|null}
     */
    private function validateContext(array $data, Centre $centre): array
    {
        $booking = isset($data['booking_id']) ? Booking::query()->lockForUpdate()->find((int) $data['booking_id']) : null;
        if (isset($data['booking_id']) && ($booking === null || $booking->centre_id !== $centre->id)) {
            throw ValidationException::withMessages(['booking_id' => 'Select a booking belonging to the selected centre.']);
        }
        $resourceId = $data['resource_id'] ?? $booking?->resource_id;
        $resource = $resourceId === null ? null : Resource::query()->lockForUpdate()->find((int) $resourceId);
        $facility = $resource === null ? null : Facility::query()->where('centre_id', $centre->id)->lockForUpdate()->find($resource->facility_id);
        if ($resourceId !== null && ($resource === null || $facility === null)) {
            throw ValidationException::withMessages(['resource_id' => 'Select a resource belonging to the selected centre.']);
        }
        if ($booking !== null && $resource->id !== $booking->resource_id) {
            throw ValidationException::withMessages(['resource_id' => 'The resource must match the booking context.']);
        }

        return [$booking, $resource];
    }
}
