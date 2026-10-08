<?php

namespace App\Http\Controllers;

use App\Actions\AmendBooking;
use App\Actions\CancelBooking;
use App\Enums\OrganisationRole;
use App\Exceptions\BookingLifecycleTransitionUnavailable;
use App\Exceptions\BookingSubmissionUnavailable;
use App\Http\Requests\AmendBookingRequest;
use App\Http\Requests\CancelBookingRequest;
use App\Models\Booking;
use App\Models\User;
use App\Services\BookingDateTime;
use App\Services\CustomerBookingPresenter;
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

        $organisationIds = $customer->organisationMemberships()
            ->whereIn('role', collect(OrganisationRole::cases())
                ->filter(fn (OrganisationRole $role): bool => $role->canViewBookings())
                ->map->value)
            ->pluck('organisation_id');

        return Inertia::render('bookings/Index', [
            'bookings' => Booking::query()
                ->where(function ($query) use ($customer, $organisationIds): void {
                    $query->where(function ($personal) use ($customer): void {
                        $personal->whereNull('organisation_id')->where('customer_id', $customer->id);
                    })->orWhereIn('organisation_id', $organisationIds);
                })
                ->with(['customer', 'organisation', 'centre', 'facility', 'resource', 'series', 'invoiceLines.invoice.organisation'])
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
        $this->ensureAccessible($customer, $booking);
        $booking->load([
            'centre',
            'facility',
            'resource',
            'customer',
            'organisation',
            'series.bookings',
            'series.bookings.organisation',
            'invoiceLines.invoice.organisation',
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
        $this->ensureAccessible($customer, $booking);

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
        $this->ensureAccessible($customer, $booking);
        $data = $request->validated();

        try {
            $amend->handle(
                actor: $customer,
                booking: $booking,
                resourceId: (int) ($data['resource_id'] ?? $booking->resource_id),
                startsAt: BookingDateTime::fromLocalInput($data['starts_at']),
                endsAt: BookingDateTime::fromLocalInput($data['ends_at']),
                scope: $data['scope'],
            );
        } catch (BookingLifecycleTransitionUnavailable|BookingSubmissionUnavailable $exception) {
            return back()->withErrors(['booking' => $exception->getMessage()]);
        }

        return to_route('bookings.show', $booking)->with('success', 'Your booking amendment was saved.');
    }

    private function ensureAccessible(User $customer, Booking $booking): void
    {
        abort_unless($customer->can('viewCustomer', $booking), 404);
    }
}
