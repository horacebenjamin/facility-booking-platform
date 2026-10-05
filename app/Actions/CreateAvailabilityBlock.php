<?php

namespace App\Actions;

use App\Enums\AvailabilityBlockScope;
use App\Enums\AvailabilityBlockType;
use App\Models\AvailabilityBlock;
use App\Models\Centre;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\User;
use App\Services\CentreReservationLock;
use App\Services\ClosureAuthorization;
use App\Services\ClosureImpactService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateAvailabilityBlock
{
    public function __construct(
        private CentreReservationLock $reservationLock,
        private ClosureAuthorization $authorization,
        private ClosureImpactService $impacts,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data): AvailabilityBlock
    {
        $actor = $this->authorization->freshActor($actor);
        Gate::forUser($actor)->authorize('create', AvailabilityBlock::class);
        if (isset($data['reason']) && is_string($data['reason'])) {
            $data['reason'] = trim($data['reason']);
        }
        if (($data['scope'] ?? null) instanceof AvailabilityBlockScope) {
            $data['scope'] = $data['scope']->value;
        }
        if (($data['type'] ?? null) instanceof AvailabilityBlockType) {
            $data['type'] = $data['type']->value;
        }
        $validated = Validator::make($data, [
            'centre_id' => ['required', 'integer', 'exists:centres,id'],
            'scope' => ['required', Rule::enum(AvailabilityBlockScope::class)],
            'facility_id' => ['nullable', 'integer', 'required_if:scope,facility,resource', 'prohibited_if:scope,centre'],
            'resource_id' => ['nullable', 'integer', 'required_if:scope,resource', 'prohibited_unless:scope,resource'],
            'type' => ['required', Rule::enum(AvailabilityBlockType::class)],
            'starts_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'ends_at' => ['required', 'date_format:Y-m-d H:i:s', 'after:starts_at'],
            'reason' => ['required', 'string', 'max:255'],
        ])->validate();
        $centre = Centre::query()->whereKey((int) $validated['centre_id'])->firstOrFail();
        $actor = $this->authorization->authorizeCentre($actor, $centre);
        $this->validateHierarchy($validated);

        return DB::transaction(function () use ($actor, $validated): AvailabilityBlock {
            $this->reservationLock->lock((int) $validated['centre_id']);
            $centre = Centre::query()->whereKey((int) $validated['centre_id'])->firstOrFail();
            $actor = $this->authorization->authorizeCentre($actor, $centre);
            $this->validateHierarchy($validated);
            $scope = AvailabilityBlockScope::from($validated['scope']);
            $block = new AvailabilityBlock;
            $block->forceFill([
                'centre_id' => $scope === AvailabilityBlockScope::Centre ? $centre->id : null,
                'facility_id' => $scope === AvailabilityBlockScope::Facility ? (int) $validated['facility_id'] : null,
                'resource_id' => $scope === AvailabilityBlockScope::Resource ? (int) $validated['resource_id'] : null,
                'type' => AvailabilityBlockType::from($validated['type']),
                'starts_at' => CarbonImmutable::parse($validated['starts_at'], config('app.timezone')),
                'ends_at' => CarbonImmutable::parse($validated['ends_at'], config('app.timezone')),
                'reason' => $validated['reason'],
                'created_by' => $actor->id,
            ])->save();
            $count = $this->impacts->detect($actor, $block, CarbonImmutable::now(config('app.timezone')));
            activity('closure')->performedOn($block)->causedBy($actor)->event('closure.created')
                ->withProperties([
                    'centre_id' => $centre->id,
                    'scope' => $scope->value,
                    'type' => $block->type->value,
                    'starts_at' => $block->starts_at->toIso8601String(),
                    'ends_at' => $block->ends_at->toIso8601String(),
                    'affected_count' => $count,
                ])->log('Availability block created');

            return $block;
        });
    }

    /** @param array<string, mixed> $data */
    private function validateHierarchy(array $data): void
    {
        if ($data['scope'] === AvailabilityBlockScope::Centre->value) {
            return;
        }
        $facility = Facility::query()->where('centre_id', (int) $data['centre_id'])->whereKey((int) $data['facility_id'])->first();
        if ($facility === null) {
            throw ValidationException::withMessages(['facility_id' => 'Select a facility belonging to the selected centre.']);
        }
        if ($data['scope'] === AvailabilityBlockScope::Resource->value
            && ! Resource::query()->where('facility_id', $facility->id)->whereKey($data['resource_id'])->exists()) {
            throw ValidationException::withMessages(['resource_id' => 'Select a resource belonging to the selected facility.']);
        }
    }
}
