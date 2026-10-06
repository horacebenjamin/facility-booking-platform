import { describe, expect, it } from 'vite-plus/test';
import { formatBookingDateTime } from '@/lib/bookingDateTime';

describe('booking date-time formatting', () => {
    it('formats stored application-timezone timestamps deterministically', () => {
        expect(formatBookingDateTime('2026-10-07T15:00:00+00:00')).toBe(
            '7 Oct 2026, 16:00',
        );
    });

    it('does not apply a daylight-saving offset to UTC booking timestamps', () => {
        expect(formatBookingDateTime('2026-01-07T15:00:00+00:00')).toBe(
            '7 Jan 2026, 15:00',
        );
    });
});
