<?php

namespace App\Http\Controllers;

use App\Actions\AmendBooking;
use App\Actions\CancelBooking;
use App\Exceptions\BookingLifecycleTransitionUnavailable;
use App\Exceptions\BookingSubmissionUnavailable;
use App\Http\Requests\AmendBookingRequest;
use App\Http\Requests\CancelBookingRequest;
use App\Models\Booking;
use App\Models\User;
use App\Services\CustomerBookingPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerBookingController extends Controller
{
    public function index(Request $request, CustomerBookingPresenter $presenter): Response
    {
        /** @var User $customer */
        $customer = $request->user();
        abort_unless($customer->can('bookings.view'), 403);

        return Inertia::render('bookings/Index', [
            'bookings' => $customer->bookings()
                ->with(['centre', 'facility', 'resource', 'series', 'invoiceLines.invoice'])
                ->latest('starts_at')
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (Booking $booking): array => $presenter->summary($booking, $customer))
                ->values(),
        ]);
    }

    public function show(Request $request, Booking $booking, CustomerBookingPresenter $presenter): Response
    {
        /** @var User $customer */
        $customer = $request->user();
        $this->ensureOwned($customer, $booking);
        $booking->load([
            'centre',
            'facility',
            'resource',
            'series.bookings',
            'invoiceLines' => fn ($query) => $query
                ->whereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('customer_id', $customer->id))
                ->with('invoice'),
            'activities',
        ]);

        return Inertia::render('bookings/Show', [
            'booking' => $presenter->detail($booking, $customer),
        ]);
    }

    public function cancel(CancelBookingRequest $request, Booking $booking, CancelBooking $cancel): RedirectResponse
    {
        /** @var User $customer */
        $customer = $request->user();
        $this->ensureOwned($customer, $booking);

        try {
            $cancel->handle($customer, $booking, $request->validated('reason'));
        } catch (BookingLifecycleTransitionUnavailable $exception) {
            return back()->withErrors(['booking' => $exception->getMessage()]);
        }

        return to_route('bookings.show', $booking)->with('success', 'Your booking has been cancelled.');
    }

    public function amend(AmendBookingRequest $request, Booking $booking, AmendBooking $amend): RedirectResponse
    {
        /** @var User $customer */
        $customer = $request->user();
        $this->ensureOwned($customer, $booking);
        $data = $request->validated();

        try {
            $amend->handle(
                actor: $customer,
                booking: $booking,
                resourceId: (int) ($data['resource_id'] ?? $booking->resource_id),
                startsAt: CarbonImmutable::parse($data['starts_at'], config('app.timezone')),
                endsAt: CarbonImmutable::parse($data['ends_at'], config('app.timezone')),
                scope: $data['scope'],
            );
        } catch (BookingLifecycleTransitionUnavailable|BookingSubmissionUnavailable $exception) {
            return back()->withErrors(['booking' => $exception->getMessage()]);
        }

        return to_route('bookings.show', $booking)->with('success', 'Your booking amendment was saved.');
    }

    private function ensureOwned(User $customer, Booking $booking): void
    {
        abort_unless($customer->id === $booking->customer_id, 404);
    }
}
