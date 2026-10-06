const bookingDateTimeFormatter = new Intl.DateTimeFormat('en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Europe/London',
});

export function formatBookingDateTime(value: string): string {
    return bookingDateTimeFormatter.format(new Date(value));
}
