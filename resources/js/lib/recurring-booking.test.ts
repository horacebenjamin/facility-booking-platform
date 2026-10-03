import { describe, expect, it, vi } from 'vite-plus/test';
import {
    canSubmitRecurringBooking,
    recurringBookingSelectionPayload,
    recurringBookingSubmissionPayload,
    sendRecurringBookingPreview,
    sendRecurringBookingRequest,
    type BookingRequestClient,
} from '@/lib/booking';
import type {
    BookingReviewSelection,
    RecurringBookingPreviewData,
} from '@/types/booking';

const selection: BookingReviewSelection = {
    resource_id: 12,
    centre_name: 'Riverside Centre',
    facility_name: 'Sports Hall',
    resource_name: 'Court 1',
    starts_at: '2026-10-05 18:00:00',
    ends_at: '2026-10-05 19:30:00',
    duration_seconds: 5400,
    equipment: [
        {
            equipment_id: 7,
            name: 'Badminton nets',
            quantity: 2,
        },
    ],
};

const allValidPreview: RecurringBookingPreviewData = {
    pattern: {
        frequency: 'weekly',
        interval_weeks: 1,
        occurrence_count: 3,
        timezone: 'Europe/London',
    },
    summary: {
        cadence: 'Every week for 3 bookings',
        date_range: '5 Oct 2026 → 19 Oct 2026',
    },
    occurrences: [],
    valid_occurrence_indexes: [1, 2, 3],
    conflict_count: 0,
};

describe('recurring booking request state', () => {
    it('maps customer inputs to the supported weekly recurrence payload only', () => {
        const payload = recurringBookingSelectionPayload(
            selection,
            2,
            8,
            'Europe/London',
        );

        expect(payload).toEqual({
            resource_id: 12,
            starts_at: '2026-10-05 18:00:00',
            ends_at: '2026-10-05 19:30:00',
            equipment: [{ equipment_id: 7, quantity: 2 }],
            interval_weeks: 2,
            occurrence_count: 8,
            timezone: 'Europe/London',
        });
        expect(payload).not.toHaveProperty('recurrence_frequency');
        expect(payload).not.toHaveProperty('price');
        expect(payload).not.toHaveProperty('status');
    });

    it('requires explicit acceptance before a conflicted preview can submit', () => {
        const conflictedPreview = {
            ...allValidPreview,
            valid_occurrence_indexes: [1, 3],
            conflict_count: 1,
        };

        expect(canSubmitRecurringBooking(allValidPreview, false)).toBe(true);
        expect(canSubmitRecurringBooking(conflictedPreview, false)).toBe(false);
        expect(canSubmitRecurringBooking(conflictedPreview, true)).toBe(true);
    });

    it('sends explicit valid occurrence indexes only for available-date resolution', () => {
        const recurringSelection = recurringBookingSelectionPayload(
            selection,
            1,
            3,
            'Europe/London',
        );

        expect(
            recurringBookingSubmissionPayload(
                recurringSelection,
                'all_occurrences',
                [1, 2, 3],
            ),
        ).toEqual({
            ...recurringSelection,
            submission_mode: 'all_occurrences',
        });
        expect(
            recurringBookingSubmissionPayload(
                recurringSelection,
                'available_occurrences',
                [1, 3],
            ),
        ).toEqual({
            ...recurringSelection,
            submission_mode: 'available_occurrences',
            selected_occurrence_indexes: [1, 3],
        });
    });

    it('uses the recurring preview and submission Wayfinder endpoints', async () => {
        const request: BookingRequestClient = vi.fn(async () => {
            return new Response(null, { status: 200 });
        });
        const recurringSelection = recurringBookingSelectionPayload(
            selection,
            1,
            3,
            'Europe/London',
        );
        const submission = recurringBookingSubmissionPayload(
            recurringSelection,
            'available_occurrences',
            [1, 3],
        );

        await sendRecurringBookingPreview(
            recurringSelection,
            'csrf-token',
            request,
        );
        await sendRecurringBookingRequest(submission, 'csrf-token', request);

        expect(request).toHaveBeenNthCalledWith(
            1,
            '/bookings/recurring/preview',
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': 'csrf-token',
                },
                body: JSON.stringify(recurringSelection),
            },
        );
        expect(request).toHaveBeenNthCalledWith(2, '/bookings/recurring', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': 'csrf-token',
            },
            body: JSON.stringify(submission),
        });
    });
});
