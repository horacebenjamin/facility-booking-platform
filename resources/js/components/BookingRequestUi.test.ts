import { renderToString } from '@vue/server-renderer';
import { createSSRApp } from 'vue';
import { describe, expect, it } from 'vite-plus/test';
import BookingConfirmation from '@/components/BookingConfirmation.vue';
import BookingSummary from '@/components/BookingSummary.vue';
import type {
    BookingReviewQuote,
    BookingReviewSelection,
} from '@/types/booking';

const selection: BookingReviewSelection = {
    resource_id: 12,
    centre_name: 'Riverside Centre',
    facility_name: 'Sports Hall',
    resource_name: 'Whole Hall',
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
const quote: BookingReviewQuote = {
    currency: 'GBP',
    total_minor: 9000,
};

describe('customer booking request presentation', () => {
    it('renders the selection customer and informational price for review', async () => {
        const app = createSSRApp(BookingSummary, {
            selection,
            quote,
            customer: {
                name: 'Alex Customer',
                email: 'alex@example.test',
            },
        });

        const html = await renderToString(app);

        expect(html).toContain('Riverside Centre');
        expect(html).toContain('Sports Hall');
        expect(html).toContain('Whole Hall');
        expect(html).toContain('Badminton nets × 2');
        expect(html).toContain('Alex Customer');
        expect(html).toContain('alex@example.test');
        expect(html).toContain('£90.00');
        expect(html).toContain('Informational until the request is submitted.');
    });

    it('renders the reference and awaiting approval confirmation state', async () => {
        const app = createSSRApp(BookingConfirmation, {
            booking: {
                reference: 'BKG-12345678',
                status: 'requested',
                status_label: 'Requested / Awaiting Management Approval',
                organisation_name: null,
                booked_by_name: 'Alex Example',
            },
            selection,
            quote,
        });

        const html = await renderToString(app);

        expect(html).toContain('Booking request received');
        expect(html.replace(/<[^>]+>/g, '').replace(/\s+/g, ' ')).toContain(
            'Booking for: Alex Example',
        );
        expect(html).toContain('BKG-12345678');
        expect(html).toContain('Requested / Awaiting Management Approval');
        expect(html).toContain('provisional reservation');
        expect(html).not.toContain('Confirmed');
        expect(html).not.toContain('Organisation');
    });

    it('shows the responsible organisation and booker for organisation bookings', async () => {
        const app = createSSRApp(BookingConfirmation, {
            booking: {
                reference: 'BKG-12345678',
                status: 'requested',
                status_label: 'Requested / Awaiting Management Approval',
                organisation_name: 'Riverside Rowing Club',
                booked_by_name: 'Alex Example',
            },
            selection,
            quote,
        });

        const html = await renderToString(app);

        expect(html).toContain('Riverside Rowing Club');
        expect(html).toContain('Booked by Alex Example');
        expect(html).not.toContain('Booking for:');
    });
});
