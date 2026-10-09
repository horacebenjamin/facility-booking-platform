import { describe, expect, it } from 'vite-plus/test';
import { formatInvoiceDate, shortInvoiceReference } from '@/lib/invoice';

describe('shortInvoiceReference', () => {
    it('abbreviates generated UUID-based references for display', () => {
        expect(
            shortInvoiceReference('INV-3f2a9c1e-7b44-4d0a-9e61-0c5d8a2b7f13'),
        ).toBe('INV-3f2a9c1e…');
    });

    it('leaves any other reference unchanged', () => {
        expect(shortInvoiceReference('INV-2026-0042')).toBe('INV-2026-0042');
        expect(shortInvoiceReference('')).toBe('');
    });
});

describe('formatInvoiceDate', () => {
    it('formats date-only values as UK dates', () => {
        expect(formatInvoiceDate('2026-10-04')).toBe('4 Oct 2026');
        expect(formatInvoiceDate('2026-12-31')).toBe('31 Dec 2026');
    });
});
