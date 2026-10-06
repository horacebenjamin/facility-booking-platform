<?php

namespace App\Actions;

use App\Enums\DamageFinancialFollowUp;
use App\Enums\DamageResponsibility;
use App\Enums\OperationalIssueStatus;
use App\Models\DamageReport;
use App\Models\User;
use App\Services\ClosureAuthorization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReviewDamageReport
{
    public function __construct(private ClosureAuthorization $authorization) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, DamageReport $report, array $data): DamageReport
    {
        $actor = $this->authorization->freshActor($actor);
        $report = DamageReport::query()->whereKey($report->id)->firstOrFail();
        Gate::forUser($actor)->authorize('review', $report);
        $data = Validator::make($data, [
            'status' => ['required', Rule::enum(OperationalIssueStatus::class)],
            'responsibility' => ['required', Rule::enum(DamageResponsibility::class)],
            'financial_follow_up' => ['required', Rule::enum(DamageFinancialFollowUp::class)],
            'follow_up_notes' => ['required', 'string', 'max:5000'],
        ])->validate();
        $target = OperationalIssueStatus::from($data['status']);

        return DB::transaction(function () use ($actor, $report, $target, $data): DamageReport {
            $report = DamageReport::query()->lockForUpdate()->findOrFail($report->id);
            Gate::forUser($actor)->authorize('review', $report);
            if (! in_array($target, $report->status->nextStates(), true)) {
                throw ValidationException::withMessages(['status' => 'Choose the next permitted issue state.']);
            }
            $before = $report->status;
            $now = CarbonImmutable::now(config('app.timezone'));
            $attributes = [
                'status' => $target,
                'responsibility' => DamageResponsibility::from($data['responsibility']),
                'financial_follow_up' => DamageFinancialFollowUp::from($data['financial_follow_up']),
                'follow_up_notes' => trim($data['follow_up_notes']),
            ];
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
            $report->forceFill($attributes)->save();
            activity('damage')->performedOn($report)->causedBy($actor)->event('damage.status_changed')
                ->withProperties(['centre_id' => $report->centre_id, 'before' => $before->value, 'after' => $target->value, 'responsibility' => $report->responsibility->value, 'financial_follow_up' => $report->financial_follow_up->value, 'follow_up_notes' => $report->follow_up_notes])
                ->log('Damage report follow-up state changed');

            return $report;
        });
    }
}
