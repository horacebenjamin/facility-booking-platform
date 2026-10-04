<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

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
        return $user->can('bookings.view') && $user->id === $invoice->customer_id;
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
        return $invoice->lines()->exists() && ! $invoice->lines()->whereHas('booking', fn (Builder $query): Builder => $query
            ->whereNotIn('centre_id', $user->assignedCentres()->select('centres.id')))->exists();
    }
}
