<?php

namespace App\Actions;

use App\Enums\OperationalIssueStatus;
use App\Models\Incident;
use App\Models\User;
use App\Services\ClosureAuthorization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReviewIncident
{
    public function __construct(private ClosureAuthorization $authorization) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, Incident $incident, array $data): Incident
    {
        $actor = $this->authorization->freshActor($actor);
        $incident = Incident::query()->whereKey($incident->id)->firstOrFail();
        Gate::forUser($actor)->authorize('review', $incident);
        $data = Validator::make($data, [
            'status' => ['required', Rule::enum(OperationalIssueStatus::class)],
            'follow_up_notes' => ['required', 'string', 'max:5000'],
        ])->validate();
        $target = OperationalIssueStatus::from($data['status']);

        return DB::transaction(function () use ($actor, $incident, $target, $data): Incident {
            $incident = Incident::query()->lockForUpdate()->findOrFail($incident->id);
            Gate::forUser($actor)->authorize('review', $incident);
            if (! in_array($target, $incident->status->nextStates(), true)) {
                throw ValidationException::withMessages(['status' => 'Choose the next permitted issue state.']);
            }
            $before = $incident->status;
            $now = CarbonImmutable::now(config('app.timezone'));
            $attributes = ['status' => $target, 'follow_up_notes' => trim($data['follow_up_notes'])];
            if ($target === OperationalIssueStatus::Reviewed) {
                $attributes['reviewed_at'] = $now;
                $attributes['reviewed_by'] = $actor->id;
            } elseif ($target === OperationalIssueStatus::Resolved) {
                $attributes['resolved_at'] = $now;
                $attributes['resolved_by'] = $actor->id;
            } else {
                $attributes['closed_at'] = $now;
                $attributes['closed_by'] = $actor->id;
            }
            $incident->forceFill($attributes)->save();
            activity('incident')->performedOn($incident)->causedBy($actor)->event('incident.status_changed')
                ->withProperties(['centre_id' => $incident->centre_id, 'before' => $before->value, 'after' => $target->value, 'follow_up_notes' => $incident->follow_up_notes])
                ->log('Incident follow-up state changed');

            return $incident;
        });
    }
}
