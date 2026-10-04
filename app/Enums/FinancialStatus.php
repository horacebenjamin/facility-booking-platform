<?php

namespace App\Enums;

enum FinancialStatus: string
{
    case NotDue = 'not_due';
    case AwaitingPayment = 'awaiting_payment';
    case InvoiceOutstanding = 'invoice_outstanding';
    case Invoiced = 'invoiced';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::NotDue => 'Not due',
            self::AwaitingPayment => 'Awaiting payment',
            self::InvoiceOutstanding => 'Outstanding under invoice terms',
            self::Invoiced => 'Invoiced — outstanding',
            self::Paid => 'Paid',
        };
    }
}
