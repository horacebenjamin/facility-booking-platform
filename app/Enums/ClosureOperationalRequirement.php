<?php

namespace App\Enums;

enum ClosureOperationalRequirement: string
{
    case ReviewRequired = 'review_required';
    case NoActionRequired = 'no_action_required';
    case RescheduleRequired = 'reschedule_required';
    case AlternativeResourceRequired = 'alternative_resource_required';
    case AlternativeCentreRequired = 'alternative_centre_required';
    case CancellationRequired = 'cancellation_required';

    public function label(): string
    {
        return match ($this) {
            self::ReviewRequired => 'Review required',
            self::NoActionRequired => 'No operational action required',
            self::RescheduleRequired => 'Reschedule required',
            self::AlternativeResourceRequired => 'Alternative resource required',
            self::AlternativeCentreRequired => 'Alternative centre required',
            self::CancellationRequired => 'Cancellation required',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $requirement) {
            $options[$requirement->value] = $requirement->label();
        }

        return $options;
    }
}
