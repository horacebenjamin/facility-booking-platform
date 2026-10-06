<?php

namespace App\Http\Controllers;

use App\Actions\InitiateBookingPayment;
use App\Enums\BillingMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentUnavailable;
use App\Http\Requests\InitiateBookingPaymentRequest;
use App\Models\Booking;
use App\Models\User;
use App\Services\BookingPaymentEligibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class BookingPaymentController extends Controller
{
    public function show(Request $request, Booking $booking, BookingPaymentEligibility $eligibility): Response|RedirectResponse
    {
        abort_unless($request->user()?->can('viewPayment', $booking), 404);
        if ($booking->billing_method === BillingMethod::Invoice) {
            $line = $booking->invoiceLines()->first();

            return $line === null ? redirect()->route('bookings.index') : redirect()->route('invoices.show', $line->invoice_id);
        }
        $payment = $booking->payments()->latest('id')->first();
        $unavailableReason = null;
        $amountMinor = null;
        $currency = null;

        try {
            $snapshot = $eligibility->snapshot($booking);
            $amountMinor = $snapshot->final_total_minor;
            $currency = $snapshot->currency;
            $eligibility->assertLifecycle($booking, now());
            $eligibility->assertProtection($booking);

            if ($booking->payment_due_at === null || $booking->payment_due_at->lte(now())) {
                throw new PaymentUnavailable('The payment deadline has passed. Please contact the centre.');
            }

            if ($payment?->status === PaymentStatus::Succeeded
                || ($payment !== null && in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Processing], true)
                    && $payment->session_expires_at->lte(now()))) {
                throw new PaymentUnavailable('A previous payment requires reconciliation. Please contact the centre.');
            }

            if (($payment === null || in_array($payment->status, [PaymentStatus::Failed, PaymentStatus::Expired], true))
                && $booking->payment_due_at->lt(now()->addMinutes(30))) {
                throw new PaymentUnavailable('A new secure checkout needs at least 30 minutes before the payment deadline.');
            }
        } catch (PaymentUnavailable $exception) {
            $unavailableReason = $exception->getMessage();
        }

        return Inertia::render('bookings/Payment', [
            'booking' => [
                'id' => $booking->id,
                'reference' => $booking->reference,
                'status' => $booking->status->value,
                'status_label' => $booking->status->label(),
                'financial_status' => $booking->financial_status->value,
                'financial_status_label' => $booking->financial_status->label(),
                'resource_name' => $booking->resource->name,
                'starts_at' => $booking->starts_at->toIso8601String(),
                'ends_at' => $booking->ends_at->toIso8601String(),
                'payment_due_at' => $booking->payment_due_at?->toIso8601String(),
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'can_pay' => $unavailableReason === null && $request->user()->can('pay', $booking),
                'unavailable_reason' => $unavailableReason,
            ],
            'payment' => $payment === null ? null : [
                'status' => $payment->status->value,
                'requires_review' => $payment->reconciliation_issue !== null,
            ],
        ]);
    }

    public function store(InitiateBookingPaymentRequest $request, Booking $booking, InitiateBookingPayment $initiate): JsonResponse|HttpResponse
    {
        /** @var User $customer */
        $customer = $request->user();

        try {
            $payment = $initiate->handle($customer, $booking);
        } catch (PaymentUnavailable $exception) {
            if ($request->header('X-Inertia')) {
                return back()->withErrors(['payment' => $exception->getMessage()]);
            }

            return response()->json(['message' => $exception->getMessage()], 409);
        }

        if ($request->header('X-Inertia')) {
            return Inertia::location($payment->checkout_url ?? route('bookings.payment.show', $booking));
        }

        return response()->json(['data' => [
            'checkout_url' => $payment->checkout_url,
            'status' => $payment->status->value,
        ]]);
    }
}
