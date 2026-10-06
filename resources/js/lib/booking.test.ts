import { describe, expect, it, vi } from 'vite-plus/test';
import {
    bookingReviewQuery,
    bookingSelectionFromSearch,
    bookingSelectionKey,
    bookingSubmissionError,
    bookingSubmissionPayload,
    canContinueToBooking,
    canStartBookingSubmission,
    isBookingSubmissionResponse,
    sendBookingRequest,
    withBookingOrganisation,
    type BookingRequestClient,
} from '@/lib/booking';
import { review as reviewBooking } from '@/routes/bookings';
import type {
    BookingReviewSelection,
    BookingSelectionPayload,
} from '@/types/booking';

const selection: BookingSelectionPayload = {
    resource_id: 12,
    starts_at: '2026-10-05 18:00:00',
    ends_at: '2026-10-05 19:30:00',
    equipment: [{ equipment_id: 7, quantity: 2 }],
};

describe('customer booking request state', () => {
    it('allows continuation for an available selection with a current quote', () => {
        expect(
            canContinueToBooking({
                available: true,
                hasQuote: true,
                quoteMatchesSelection: true,
                isChecking: false,
                isQuoting: false,
                quoteError: '',
            }),
        ).toBe(true);
    });

    it('blocks continuation when availability is unavailable', () => {
        expect(
            canContinueToBooking({
                available: false,
                hasQuote: true,
                quoteMatchesSelection: true,
                isChecking: false,
                isQuoting: false,
                quoteError: '',
            }),
        ).toBe(false);
    });

    it('blocks continuation when pricing failed', () => {
        expect(
            canContinueToBooking({
                available: true,
                hasQuote: false,
                quoteMatchesSelection: false,
                isChecking: false,
                isQuoting: false,
                quoteError: 'Pricing is unavailable.',
            }),
        ).toBe(false);
    });

    it('marks a quote stale after the resource time or equipment changes', () => {
        const originalKey = bookingSelectionKey(selection);

        expect(bookingSelectionKey({ ...selection, resource_id: 13 })).not.toBe(
            originalKey,
        );
        expect(
            bookingSelectionKey({
                ...selection,
                ends_at: '2026-10-05 20:00:00',
            }),
        ).not.toBe(originalKey);
        expect(
            bookingSelectionKey({
                ...selection,
                equipment: [{ equipment_id: 7, quantity: 3 }],
            }),
        ).not.toBe(originalKey);
    });

    it('restores customer-controlled selection fields from a review URL', () => {
        const reviewUrl = reviewBooking({
            query: bookingReviewQuery(selection),
        }).url;

        expect(
            bookingSelectionFromSearch(reviewUrl.slice(reviewUrl.indexOf('?'))),
        ).toEqual(selection);
    });

    it('builds review and submission data from customer-controlled inputs only', () => {
        const reviewSelection: BookingReviewSelection = {
            ...selection,
            centre_name: 'Riverside Centre',
            facility_name: 'Sports Hall',
            resource_name: 'Whole Hall',
            duration_seconds: 5400,
            equipment: [
                {
                    equipment_id: 7,
                    name: 'Badminton nets',
                    quantity: 2,
                },
            ],
        };

        expect(bookingReviewQuery(selection)).toEqual({
            resource_id: 12,
            starts_at: '2026-10-05 18:00:00',
            ends_at: '2026-10-05 19:30:00',
            equipment: {
                0: {
                    equipment_id: 7,
                    quantity: 2,
                },
            },
        });
        expect(bookingSubmissionPayload(reviewSelection)).toEqual(selection);
        expect(Object.keys(bookingSubmissionPayload(reviewSelection))).toEqual([
            'resource_id',
            'starts_at',
            'ends_at',
            'equipment',
        ]);
    });

    it('posts the exact customer payload to the existing booking endpoint', async () => {
        const request: BookingRequestClient = vi.fn(async () => {
            return new Response(null, { status: 201 });
        });

        await sendBookingRequest(selection, 'csrf-token', request);

        expect(request).toHaveBeenCalledOnce();
        expect(request).toHaveBeenCalledWith('/bookings', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': 'csrf-token',
            },
            body: JSON.stringify(selection),
        });
    });

    it('adds the chosen organisation only when booking for an organisation', () => {
        expect(withBookingOrganisation(selection, null)).toEqual(selection);
        expect(withBookingOrganisation(selection, 4)).toEqual({
            ...selection,
            organisation_id: 4,
        });
    });

    it('prevents a second submission while one is processing or completed', () => {
        expect(canStartBookingSubmission(false, false)).toBe(true);
        expect(canStartBookingSubmission(true, false)).toBe(false);
        expect(canStartBookingSubmission(false, true)).toBe(false);
    });

    it('accepts the requested response and presents conflicts with safe copy', () => {
        expect(
            isBookingSubmissionResponse({
                data: {
                    reference: 'BKG-12345678',
                    status: 'requested',
                    status_label: 'Requested / Awaiting Management Approval',
                },
            }),
        ).toBe(true);
        expect(bookingSubmissionError(409)).toBe(
            'This time is no longer available. Return to availability and choose another time.',
        );
    });
});
