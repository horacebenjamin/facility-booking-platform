<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Documents\DocumentBuilder;
use App\Services\Documents\DocumentFormatter;
use App\Services\Documents\PdfDocument;
use Illuminate\Http\Response;

class ManagementDocumentController extends Controller
{
    public function invoice(Invoice $invoice, DocumentBuilder $builder, DocumentFormatter $format, PdfDocument $pdf): Response
    {
        abort_unless($this->manager()->can('view', $invoice), 403);

        return $pdf->download('invoice', $builder->invoice($invoice), $format->filename('invoice', $invoice->reference));
    }

    public function receipt(Payment $payment, DocumentBuilder $builder, DocumentFormatter $format, PdfDocument $pdf): Response
    {
        $manager = $this->manager();
        abort_unless($manager->can('payments.view'), 403);
        abort_unless($payment->invoice_id !== null
            ? $manager->can('view', $payment->invoice)
            : ($payment->booking !== null && $manager->can('view', $payment->booking)), 403);

        return $pdf->download('receipt', $builder->receipt($payment), $format->filename('receipt', $payment->reference));
    }

    public function confirmation(Booking $booking, DocumentBuilder $builder, DocumentFormatter $format, PdfDocument $pdf): Response
    {
        abort_unless($this->manager()->can('view', $booking), 403);

        return $pdf->download('confirmation', $builder->confirmation($booking), $format->filename('booking', $booking->reference));
    }

    private function manager(): User
    {
        /** @var User $user */
        $user = auth()->user();
        abort_unless($user->hasRole('manager'), 403);

        return $user;
    }
}
