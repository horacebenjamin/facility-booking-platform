<?php

namespace App\Actions;

use App\Enums\DamageFinancialFollowUp;
use App\Enums\DamageResponsibility;
use App\Enums\OperationalIssueStatus;
use App\Models\Booking;
use App\Models\Centre;
use App\Models\DamageReport;
use App\Models\Equipment;
use App\Models\Resource;
use App\Models\User;
use App\Services\ClosureAuthorization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateDamageReport
{
    public function __construct(private ClosureAuthorization $authorization) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data): DamageReport
    {
        $actor = $this->authorization->freshActor($actor);
        $data = Validator::make($data, [
            'centre_id' => ['required', 'integer', 'exists:centres,id'],
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
            'resource_id' => ['nullable', 'integer', 'exists:resources,id'],
            'equipment_id' => ['nullable', 'integer', 'exists:equipment,id'],
            'description' => ['required', 'string', 'max:5000'],
            'observed_at' => ['required', 'date'],
        ])->validate();
        $centre = Centre::query()->findOrFail((int) $data['centre_id']);
        Gate::forUser($actor)->authorize('create', [DamageReport::class, $centre]);
        [$booking, $resource, $equipment] = $this->validateContext($data, $centre);

        return DB::transaction(function () use ($actor, $data, $centre, $booking, $resource, $equipment): DamageReport {
            $report = new DamageReport;
            $report->forceFill([
                'centre_id' => $centre->id,
                'booking_id' => $booking?->id,
                'resource_id' => $resource?->id,
                'equipment_id' => $equipment?->id,
                'reported_by' => $actor->id,
                'description' => trim($data['description']),
                'observed_at' => CarbonImmutable::parse($data['observed_at'], config('app.timezone')),
                'status' => OperationalIssueStatus::Open,
                'responsibility' => DamageResponsibility::Undetermined,
                'financial_follow_up' => DamageFinancialFollowUp::NotRequired,
            ])->save();
            activity('damage')->performedOn($report)->causedBy($actor)->event('damage.created')
                ->withProperties(['centre_id' => $centre->id, 'booking_id' => $booking?->id, 'resource_id' => $resource?->id, 'equipment_id' => $equipment?->id, 'observed_at' => $report->observed_at->toIso8601String()])
                ->log('Damage report recorded');

            return $report;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Booking|null, 1: resource|null, 2: Equipment|null}
     */
    private function validateContext(array $data, Centre $centre): array
    {
        $booking = isset($data['booking_id']) ? Booking::query()->find((int) $data['booking_id']) : null;
        if ($data['booking_id'] !== null && ($booking === null || $booking->centre_id !== $centre->id)) {
            throw ValidationException::withMessages(['booking_id' => 'Select a booking belonging to the selected centre.']);
        }
        $resourceId = $data['resource_id'] ?? $booking?->resource_id;
        $resource = $resourceId === null ? null : Resource::query()->with('facility')->find((int) $resourceId);
        if ($resourceId !== null && ($resource === null || $resource->facility->centre_id !== $centre->id)) {
            throw ValidationException::withMessages(['resource_id' => 'Select a resource belonging to the selected centre.']);
        }
        if ($booking !== null && $resource->id !== $booking->resource_id) {
            throw ValidationException::withMessages(['resource_id' => 'The resource must match the booking context.']);
        }
        $equipmentId = $data['equipment_id'] ?? null;
        $equipment = $equipmentId === null ? null : Equipment::query()->find((int) $equipmentId);
        if ($equipmentId !== null && ($equipment === null || $equipment->centre_id !== $centre->id)) {
            throw ValidationException::withMessages(['equipment_id' => 'Select equipment belonging to the selected centre.']);
        }

        return [$booking, $resource, $equipment];
    }
}
