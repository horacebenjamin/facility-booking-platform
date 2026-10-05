<?php

namespace App\Enums;

enum ClosureFinancialRequirement: string
{
    case ReviewRequired = 'review_required';
    case NotRequired = 'not_required';
    case RefundRequired = 'refund_required';
    case CreditRequired = 'credit_required';
    case InvoiceAdjustmentRequired = 'invoice_adjustment_required';

    public function label(): string
    {
        return match ($this) {
            self::ReviewRequired => 'Financial review required',
            self::NotRequired => 'No financial action required',
            self::RefundRequired => 'Refund required',
            self::CreditRequired => 'Credit required',
            self::InvoiceAdjustmentRequired => 'Invoice adjustment required',
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
