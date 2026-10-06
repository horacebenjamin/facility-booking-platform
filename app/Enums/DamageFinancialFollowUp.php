<?php

namespace App\Enums;

enum DamageFinancialFollowUp: string
{
    case NotRequired = 'not_required';
    case ReviewRequired = 'review_required';
    case DecisionRecorded = 'decision_recorded';

    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'No financial follow-up required',
            self::ReviewRequired => 'Financial review required',
            self::DecisionRecorded => 'Financial decision recorded',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(self::cases(), function (array $options, self $followUp): array {
            $options[$followUp->value] = $followUp->label();

            return $options;
        }, []);
    }
}
