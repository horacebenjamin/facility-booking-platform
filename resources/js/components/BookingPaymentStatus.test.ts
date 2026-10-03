import { renderToString } from '@vue/server-renderer';
import { createSSRApp } from 'vue';
import { describe, expect, it } from 'vite-plus/test';
import BookingPaymentStatus from '@/components/BookingPaymentStatus.vue';

describe('payment status presentation', () => {
    it.each(['pending', 'processing', 'failed', 'expired'])(
        'does not confirm an approved booking for %s payment',
        async (status) => {
            const html = await renderToString(
                createSSRApp(BookingPaymentStatus, {
                    bookingStatus: 'approved',
                    financialStatus: 'awaiting_payment',
                    payment: { status, requires_review: false },
                }),
            );
            expect(html).not.toContain('Your booking is confirmed.');
            expect(html).toContain('not confirm');
        },
    );
    it('requires authoritative booking and financial states before displaying confirmation', async () => {
        const html = await renderToString(
            createSSRApp(BookingPaymentStatus, {
                bookingStatus: 'confirmed',
                financialStatus: 'paid',
                payment: { status: 'succeeded', requires_review: false },
            }),
        );
        expect(html).toContain('Payment received. Your booking is confirmed.');
    });
    it('shows review instead of confirmation for a received payment with stale booking state', async () => {
        const html = await renderToString(
            createSSRApp(BookingPaymentStatus, {
                bookingStatus: 'approved',
                financialStatus: 'awaiting_payment',
                payment: { status: 'succeeded', requires_review: true },
            }),
        );
        expect(html).toContain('needs a centre review');
        expect(html).not.toContain('Your booking is confirmed.');
    });
});
