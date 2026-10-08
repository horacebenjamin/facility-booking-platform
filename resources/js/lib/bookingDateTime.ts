export function formatBookingDateTime(value: string, timeZone: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone,
    }).format(new Date(value));
}

export function bookingLocalInput(value: string, timeZone: string): string {
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(new Date(value));
    const part = (type: Intl.DateTimeFormatPartTypes): string =>
        parts.find((item) => item.type === type)?.value ?? '';

    return `${part('year')}-${part('month')}-${part('day')}T${part('hour')}:${part('minute')}`;
}

export function bookingWallClockMinutes(
    date: string,
    startsAt: string,
    endsAt: string,
): number {
    const [year, month, day] = date.split('-').map(Number);
    const [startHour, startMinute] = startsAt.split(':').map(Number);
    const [endHour, endMinute] = endsAt.split(':').map(Number);
    const startsAtWallClock = Date.UTC(
        year,
        month - 1,
        day,
        startHour,
        startMinute,
    );
    const endsAtWallClock = Date.UTC(year, month - 1, day, endHour, endMinute);

    return (endsAtWallClock - startsAtWallClock) / 60000;
}
