<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('invoices.view') && $user->assignedCentres()->exists();
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.view') && $this->hasCentreScope($user, $invoice);
    }

    public function viewCustomer(User $user, Invoice $invoice): bool
    {
        if (! $user->hasRole('customer') || ! $user->can('bookings.view')) {
            return false;
        }

        if ($invoice->organisation_id === null) {
            return $user->id === $invoice->customer_id;
        }

        return $invoice->organisation?->membershipFor($user)?->role->canManageFinance() === true;
    }

    public function create(User $user): bool
    {
        return $user->can('invoices.manage') && $user->assignedCentres()->exists();
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $user->can('payments.record') && $this->hasCentreScope($user, $invoice);
    }

    private function hasCentreScope(User $user, Invoice $invoice): bool
    {
        $centreIds = array_values($user->assignedCentres()->pluck('centres.id')->map(fn (int $id): int => $id)->all());

        return Invoice::query()->whereKey($invoice->id)->scopedToCentres($centreIds)->exists();
    }
}
