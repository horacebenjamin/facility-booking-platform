import { renderToString } from '@vue/server-renderer';
import { createSSRApp } from 'vue';
import { describe, expect, it } from 'vite-plus/test';
import BookingFrequencyChoice from '@/components/BookingFrequencyChoice.vue';
import RecurringBookingConfirmation from '@/components/RecurringBookingConfirmation.vue';
import RecurringBookingReview from '@/components/RecurringBookingReview.vue';
import RecurringOccurrenceList from '@/components/RecurringOccurrenceList.vue';
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
    equipment: [],
};

const preview: RecurringBookingPreviewData = {
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
    valid_occurrence_indexes: [1, 3],
    conflict_count: 1,
    occurrences: [
        {
            index: 1,
            starts_at: '2026-10-05 18:00:00',
            ends_at: '2026-10-05 19:30:00',
            status: 'available',
            price: { currency: 'GBP', total_minor: 7500 },
            equipment: [],
            conflict_reasons: [],
            conflict_messages: [],
        },
        {
            index: 2,
            starts_at: '2026-10-12 18:00:00',
            ends_at: '2026-10-12 19:30:00',
            status: 'conflict',
            price: null,
            equipment: [],
            conflict_reasons: ['resource_unavailable'],
            conflict_messages: ['The resource is unavailable for this time.'],
        },
        {
            index: 3,
            starts_at: '2026-10-19 18:00:00',
            ends_at: '2026-10-19 19:30:00',
            status: 'available',
            price: { currency: 'GBP', total_minor: 7500 },
            equipment: [],
            conflict_reasons: [],
            conflict_messages: [],
        },
    ],
};

describe('recurring booking presentation', () => {
    it('offers one-off and recurring booking choices with one-off selected by default', async () => {
        const app = createSSRApp(BookingFrequencyChoice, {
            modelValue: 'one_off',
        });

        const html = await renderToString(app);

        expect(html).toContain('One-off booking');
        expect(html).toContain('Recurring booking');
        expect(html).toContain('Repeat weekly and review every date.');
        expect(html).toContain('value="one_off"');
    });

    it('shows the deliberately limited weekly recurrence inputs and timezone', async () => {
        const app = createSSRApp(RecurringBookingReview, {
            selection,
            recurrence: {
                timezone: 'Europe/London',
                minimum_interval_weeks: 1,
                maximum_interval_weeks: 255,
                minimum_occurrences: 2,
                maximum_occurrences: 104,
            },
        });

        const html = await renderToString(app);

        expect(html).toContain('Repeat every');
        expect(html).toContain('Number of bookings');
        expect(html).toContain('Europe/London');
        expect(html).toContain('Preview recurring dates');
        expect(html).not.toContain('monthly');
    });

    it('renders every available and conflicting occurrence with safe status and price copy', async () => {
        const app = createSSRApp(RecurringOccurrenceList, { preview });

        const html = await renderToString(app);

        expect(html).toContain('Every week for 3 bookings');
        expect(html).toContain('Booking 1');
        expect(html).toContain('Booking 2');
        expect(html).toContain('Booking 3');
        expect(html).toContain('Available');
        expect(html).toContain('Conflict');
        expect(html).toContain('£75.00');
        expect(html).toContain('The resource is unavailable for this time.');
        expect(html).toContain('No booking or price reserved');
    });

    it('renders the recurring awaiting-approval confirmation without confirmed language', async () => {
        const app = createSSRApp(RecurringBookingConfirmation, {
            confirmation: {
                identifier: '65d78bc1-d51a-45e9-8bd8-9758a6b22283',
                status: 'requested',
                status_label: 'Requested / Awaiting Management Approval',
                occurrence_count: 2,
                requested_occurrence_count: 3,
                first_date: '5 Oct 2026',
                last_date: '19 Oct 2026',
                timezone: 'Europe/London',
                occurrences: [
                    {
                        index: 1,
                        reference: 'BKG-12345678',
                        starts_at: '2026-10-05 18:00:00',
                        ends_at: '2026-10-05 19:30:00',
                        price: { currency: 'GBP', total_minor: 7500 },
                    },
                    {
                        index: 3,
                        reference: 'BKG-87654321',
                        starts_at: '2026-10-19 18:00:00',
                        ends_at: '2026-10-19 19:30:00',
                        price: { currency: 'GBP', total_minor: 7500 },
                    },
                ],
            },
        });

        const html = await renderToString(app);

        expect(html).toContain('Recurring booking request received');
        expect(html).toContain('Requested / Awaiting Management Approval');
        expect(html).toContain('Each booking is a separate request');
        expect(html).toContain('Occurrence 1');
        expect(html).toContain('Occurrence 3');
        expect(html).not.toContain('Confirmed');
    });
});
