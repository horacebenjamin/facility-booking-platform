<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerBookingController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('bookings.view'), 403);

        return Inertia::render('bookings/Index', [
            'bookings' => $request->user()->bookings()->with('resource')
                ->latest('starts_at')->limit(50)->get()->map(fn ($booking): array => [
                    'id' => $booking->id,
                    'reference' => $booking->reference,
                    'resource_name' => $booking->resource->name,
                    'starts_at' => $booking->starts_at->toIso8601String(),
                    'status_label' => $booking->status->label(),
                    'financial_status_label' => $booking->financial_status->label(),
                ]),
        ]);
    }
}
