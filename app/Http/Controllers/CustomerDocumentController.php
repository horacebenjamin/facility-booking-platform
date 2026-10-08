<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\Payment;
use App\Models\User;
use App\Services\Documents\DocumentBuilder;
use App\Services\Documents\DocumentFormatter;
use App\Services\Documents\PdfDocument;
use App\Services\Documents\StatementBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerDocumentController extends Controller
{
    public function invoice(Invoice $invoice, DocumentBuilder $builder, DocumentFormatter $format, PdfDocument $pdf): Response
    {
        abort_unless(auth()->user()?->can('viewCustomer', $invoice), 404);

        return $pdf->download('invoice', $builder->invoice($invoice), $format->filename('invoice', $invoice->reference));
    }

    public function receipt(Payment $payment, DocumentBuilder $builder, DocumentFormatter $format, PdfDocument $pdf): Response
    {
        abort_unless($payment->invoice_id !== null
            ? auth()->user()?->can('viewCustomer', $payment->invoice)
            : ($payment->booking !== null && auth()->user()?->can('pay', $payment->booking)), 404);

        return $pdf->download('receipt', $builder->receipt($payment), $format->filename('receipt', $payment->reference));
    }

    public function confirmation(Booking $booking, DocumentBuilder $builder, DocumentFormatter $format, PdfDocument $pdf): Response
    {
        abort_unless(auth()->user()?->can('viewCustomer', $booking), 404);

        return $pdf->download('confirmation', $builder->confirmation($booking), $format->filename('booking', $booking->reference));
    }

    public function personalStatement(Request $request, StatementBuilder $builder, PdfDocument $pdf): Response
    {
        /** @var User $owner */
        $owner = $request->user();
        abort_unless($owner->hasRole('customer') && $owner->can('bookings.view'), 403);
        [$from, $to] = $this->period($request);

        return $pdf->download('statement', $builder->build($owner, $from, $to), "statement-personal-{$from}-to-{$to}.pdf");
    }

    public function organisationStatement(Request $request, Organisation $organisation, StatementBuilder $builder, PdfDocument $pdf): Response
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('viewFinance', $organisation);
        [$from, $to] = $this->period($request);

        return $pdf->download('statement', $builder->build($organisation, $from, $to),
            'statement-organisation-'.Str::slug($organisation->name)."-{$from}-to-{$to}.pdf");
    }

    /** @return array{string, string} */
    private function period(Request $request): array
    {
        $today = CarbonImmutable::now((string) config('booking.local_timezone'));
        $data = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
        ]);
        $from = $data['from'] ?? $today->startOfMonth()->toDateString();
        $to = $data['to'] ?? $today->toDateString();
        $start = CarbonImmutable::parse($from, (string) config('booking.local_timezone'));
        $end = CarbonImmutable::parse($to, (string) config('booking.local_timezone'));
        if ($end->gt($today)) {
            throw ValidationException::withMessages(['to' => 'The statement end date cannot be in the future.']);
        }

        if ($end->lt($start) || $start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['from' => 'Choose a statement period of at most 366 days.']);
        }

        return [$from, $to];
    }
}
