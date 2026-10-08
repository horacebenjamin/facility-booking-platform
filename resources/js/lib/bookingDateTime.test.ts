import { describe, expect, it } from 'vite-plus/test';
import {
    bookingLocalInput,
    bookingWallClockMinutes,
    formatBookingDateTime,
} from '@/lib/bookingDateTime';

describe('booking date-time formatting', () => {
    it('formats stored application-timezone timestamps deterministically', () => {
        expect(
            formatBookingDateTime('2026-10-07T17:00:00+00:00', 'Europe/London'),
        ).toBe('7 Oct 2026, 18:00');
    });

    it('does not apply a daylight-saving offset to UTC booking timestamps', () => {
        expect(
            formatBookingDateTime('2026-01-07T18:00:00+00:00', 'Europe/London'),
        ).toBe('7 Jan 2026, 18:00');
    });

    it('uses London wall time when preparing a customer amendment input', () => {
        expect(
            bookingLocalInput('2026-10-07T17:00:00+00:00', 'Europe/London'),
        ).toBe('2026-10-07T18:00');
        expect(
            bookingLocalInput('2027-01-07T18:00:00+00:00', 'Europe/London'),
        ).toBe('2027-01-07T18:00');
    });

    it('calculates selected wall-clock duration without using the browser timezone', () => {
        expect(bookingWallClockMinutes('2026-10-07', '18:00', '19:30')).toBe(
            90,
        );
    });
});
