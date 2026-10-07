<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\OrganisationRole;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerInvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('bookings.view'), 403);

        $user = $request->user();
        $organisationIds = $user->organisationMemberships()
            ->whereIn('role', collect(OrganisationRole::cases())
                ->filter(fn (OrganisationRole $role): bool => $role->canManageFinance())
                ->map->value)
            ->pluck('organisation_id');

        return Inertia::render('invoices/Index', [
            'invoices' => Invoice::query()
                ->where(function ($query) use ($user, $organisationIds): void {
                    $query->where(function ($personal) use ($user): void {
                        $personal->whereNull('organisation_id')->where('customer_id', $user->id);
                    })->orWhereIn('organisation_id', $organisationIds);
                })
                ->with(['customer', 'organisation'])
                ->latest('issue_date')->latest('id')->limit(50)->get()
                ->map(fn (Invoice $invoice): array => $this->summary($invoice)),
        ]);
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        abort_unless($request->user()?->can('viewCustomer', $invoice), 404);
        $invoice->load(['customer', 'organisation', 'lines.booking']);

        return Inertia::render('invoices/Show', [
            'invoice' => [...$this->summary($invoice), 'lines' => $invoice->lines->map(fn (InvoiceLine $line): array => [
                'id' => $line->id,
                'description' => $line->description,
                'amount_minor' => $line->amount_minor,
                'booking_reference' => $line->booking->reference,
            ])],
        ]);
    }

    /** @return array<string, int|string|bool> */
    private function summary(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'reference' => $invoice->reference,
            'owner_type' => $invoice->organisation_id === null ? 'individual' : 'organisation',
            'owner_name' => $invoice->organisation->name ?? $invoice->customer->name,
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->label(),
            'currency' => $invoice->currency,
            'total_minor' => $invoice->total_minor,
            'outstanding_minor' => $invoice->status === InvoiceStatus::Paid ? 0 : $invoice->total_minor,
            'overdue' => $invoice->isOverdue(),
        ];
    }
}
