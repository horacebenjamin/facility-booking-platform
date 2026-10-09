const generatedReference =
    /^(INV-[0-9a-f]{8})-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

/**
 * Presentation-only abbreviation of a generated invoice reference. The
 * persisted reference remains authoritative and is never altered.
 */
export function shortInvoiceReference(reference: string): string {
    const match = generatedReference.exec(reference);

    return match ? `${match[1]}…` : reference;
}

/** Formats a date-only value (YYYY-MM-DD) for UK display without timezone shift. */
export function formatInvoiceDate(value: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        dateStyle: 'medium',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}
