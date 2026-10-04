<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomerInvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('bookings.view'), 403);

        return Inertia::render('invoices/Index', [
            'invoices' => $request->user()->invoices()->latest('issue_date')->limit(50)->get()
                ->map(fn (Invoice $invoice): array => $this->summary($invoice)),
        ]);
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        abort_unless($invoice->customer_id === $request->user()?->id, 404);
        Gate::authorize('viewCustomer', $invoice);
        $invoice->load('lines.booking');

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
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->label(),
            'currency' => $invoice->currency,
            'total_minor' => $invoice->total_minor,
            'outstanding_minor' => $invoice->status === InvoiceStatus::Paid ? 0 : $invoice->total_minor,
            'overdue' => $invoice->status !== InvoiceStatus::Paid && $invoice->due_date->endOfDay()->isPast(),
        ];
    }
}
