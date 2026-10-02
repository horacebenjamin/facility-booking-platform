import type {
    BookingReviewSelection,
    BookingSelectionPayload,
    BookingSubmissionResponse,
} from '@/types/booking';
import { store as storeBooking } from '@/routes/bookings';
import type { QueryParams } from '@/wayfinder';

interface BookingContinuationContext {
    available: boolean;
    hasQuote: boolean;
    quoteMatchesSelection: boolean;
    isChecking: boolean;
    isQuoting: boolean;
    quoteError: string;
}

export type BookingRequestClient = (
    input: string,
    init: RequestInit,
) => Promise<Response>;

export function bookingSelectionKey(
    selection: BookingSelectionPayload,
): string {
    return JSON.stringify({
        ...selection,
        equipment: [...selection.equipment].sort(
            (left, right) => left.equipment_id - right.equipment_id,
        ),
    });
}

export function canContinueToBooking(
    context: BookingContinuationContext,
): boolean {
    return (
        context.available &&
        context.hasQuote &&
        context.quoteMatchesSelection &&
        !context.isChecking &&
        !context.isQuoting &&
        context.quoteError === ''
    );
}

export function bookingReviewQuery(
    selection: BookingSelectionPayload,
): QueryParams {
    return {
        resource_id: selection.resource_id,
        starts_at: selection.starts_at,
        ends_at: selection.ends_at,
        equipment: Object.fromEntries(
            selection.equipment.map((item, index) => [
                index,
                {
                    equipment_id: item.equipment_id,
                    quantity: item.quantity,
                },
            ]),
        ),
    };
}

export function bookingSelectionFromSearch(
    search: string,
): BookingSelectionPayload | null {
    const parameters = new URLSearchParams(search);
    const resourceId = Number(parameters.get('resource_id'));
    const startsAt = parameters.get('starts_at');
    const endsAt = parameters.get('ends_at');

    if (
        !Number.isInteger(resourceId) ||
        resourceId <= 0 ||
        startsAt === null ||
        endsAt === null ||
        !/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(?::\d{2})?$/.test(startsAt) ||
        !/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(?::\d{2})?$/.test(endsAt)
    ) {
        return null;
    }

    const equipmentByIndex = new Map<
        string,
        Partial<{ equipment_id: number; quantity: number }>
    >();

    parameters.forEach((value, key) => {
        const match = key.match(
            /^equipment\[(\d+)]\[(equipment_id|quantity)]$/,
        );

        if (match === null) {
            return;
        }

        const [, index, field] = match;
        const numericValue = Number(value);

        if (!Number.isInteger(numericValue) || numericValue <= 0) {
            return;
        }

        equipmentByIndex.set(index, {
            ...equipmentByIndex.get(index),
            [field]: numericValue,
        });
    });

    const equipment = [...equipmentByIndex.entries()]
        .sort(([left], [right]) => Number(left) - Number(right))
        .map(([, item]) => item)
        .filter(
            (
                item,
            ): item is {
                equipment_id: number;
                quantity: number;
            } => item.equipment_id !== undefined && item.quantity !== undefined,
        );

    return {
        resource_id: resourceId,
        starts_at: startsAt,
        ends_at: endsAt,
        equipment,
    };
}

export function bookingSubmissionPayload(
    selection: BookingReviewSelection,
): BookingSelectionPayload {
    return {
        resource_id: selection.resource_id,
        starts_at: selection.starts_at,
        ends_at: selection.ends_at,
        equipment: selection.equipment.map((item) => ({
            equipment_id: item.equipment_id,
            quantity: item.quantity,
        })),
    };
}

export function canStartBookingSubmission(
    isSubmitting: boolean,
    hasSucceeded: boolean,
): boolean {
    return !isSubmitting && !hasSucceeded;
}

export function sendBookingRequest(
    selection: BookingSelectionPayload,
    csrfToken: string | undefined,
    request: BookingRequestClient = fetch,
): Promise<Response> {
    const bookingRoute = storeBooking();

    return request(bookingRoute.url, {
        method: bookingRoute.method.toUpperCase(),
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(csrfToken ? { 'X-XSRF-TOKEN': csrfToken } : {}),
        },
        body: JSON.stringify(selection),
    });
}

export function isBookingSubmissionResponse(
    payload: unknown,
): payload is BookingSubmissionResponse {
    if (
        typeof payload !== 'object' ||
        payload === null ||
        !('data' in payload) ||
        typeof payload.data !== 'object' ||
        payload.data === null
    ) {
        return false;
    }

    return (
        'reference' in payload.data &&
        'status' in payload.data &&
        'status_label' in payload.data &&
        typeof payload.data.reference === 'string' &&
        payload.data.status === 'requested' &&
        payload.data.status_label === 'Requested / Awaiting Management Approval'
    );
}

export function bookingSubmissionError(status: number): string {
    if (status === 409) {
        return 'This time is no longer available. Return to availability and choose another time.';
    }

    if (status === 422) {
        return 'Some booking details are no longer valid. Review your selection and try again.';
    }

    return 'We could not submit your booking request right now. Please try again.';
}
