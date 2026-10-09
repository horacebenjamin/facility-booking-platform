import { renderToString } from '@vue/server-renderer';
import { createSSRApp, defineComponent, h } from 'vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import NotificationIndex from '@/pages/notifications/Index.vue';
import type { CustomerNotification } from '@/types/notification';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { bookingTimezone: 'Europe/London' } }),
    Head: defineComponent({ render: () => null }),
    Link: defineComponent({
        props: ['href'],
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
    Form: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('form', slots.default?.({ processing: false })),
    }),
}));

const notification: CustomerNotification = {
    id: 'c583f350-fc14-4e28-8465-e630e804e79e',
    type: 'booking_approved_payment_required',
    title: 'Booking approved: payment required',
    body: 'Your booking is approved. Payment is required before confirmation.',
    action_label: 'Pay booking',
    action_url: '/bookings/1/payment',
    occurred_at: '2026-10-04T12:00:00.000Z',
    created_at: '2026-10-04T12:00:00.000Z',
    read_at: null,
};

function render(items: CustomerNotification[]): Promise<string> {
    return renderToString(
        createSSRApp(NotificationIndex, {
            notifications: {
                data: items,
                current_page: 1,
                last_page: 1,
                prev_page_url: null,
                next_page_url: null,
            },
            unread_count: items.filter((item) => !item.read_at).length,
        }),
    );
}

describe('customer notification presentation', () => {
    it('shows authoritative payment-required wording, link and unread interaction', async () => {
        const html = await render([notification]);
        expect(html).toContain('Payment is required before confirmation.');
        expect(html).toContain('href="/bookings/1/payment"');
        expect(html).toContain('Mark as read');
        expect(html).toContain('Unread');
        expect(html).not.toContain('Your booking is confirmed');
    });

    it('escapes customer-visible text and does not offer another read action for read notifications', async () => {
        const html = await render([
            {
                ...notification,
                title: '<script>private()</script>',
                read_at: notification.occurred_at,
            },
        ]);
        expect(html).toContain('&lt;script&gt;private()&lt;/script&gt;');
        expect(html).not.toContain('<script>private()</script>');
        expect(html).not.toContain('Mark as read');
    });

    it('shows an accessible empty notification centre', async () => {
        const html = await render([]);
        expect(html).toContain('You have no notifications yet.');
        expect(html).not.toContain('Mark as read');
    });
});
