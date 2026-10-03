export interface BookingEquipmentSelection {
    equipment_id: number;
    quantity: number;
}

export interface BookingSelectionPayload {
    resource_id: number;
    starts_at: string;
    ends_at: string;
    equipment: BookingEquipmentSelection[];
}

export interface BookingReviewSelection extends BookingSelectionPayload {
    centre_name: string;
    facility_name: string;
    resource_name: string;
    duration_seconds: number;
    equipment: Array<BookingEquipmentSelection & { name: string }>;
}

export interface BookingReviewQuote {
    currency: string;
    total_minor: number;
}

export interface BookingCustomer {
    name: string;
    email: string;
}

export interface BookingSubmissionResponse {
    data: {
        reference: string;
        status: 'requested';
        status_label: 'Requested / Awaiting Management Approval';
    };
}

export interface RecurrenceConstraints {
    timezone: string;
    minimum_interval_weeks: number;
    maximum_interval_weeks: number;
    minimum_occurrences: number;
    maximum_occurrences: number;
}

export interface RecurringBookingSelectionPayload extends BookingSelectionPayload {
    interval_weeks: number;
    occurrence_count: number;
    timezone: string;
}

export interface RecurringBookingSubmissionPayload extends RecurringBookingSelectionPayload {
    submission_mode: 'all_occurrences' | 'available_occurrences';
    selected_occurrence_indexes?: number[];
}

export interface RecurringBookingOccurrence {
    index: number;
    starts_at: string;
    ends_at: string;
    status: 'available' | 'conflict';
    price: BookingReviewQuote | null;
    equipment: Array<BookingEquipmentSelection & { name: string }>;
    conflict_reasons: string[];
    conflict_messages: string[];
}

export interface RecurringBookingPreviewData {
    pattern: {
        frequency: 'weekly';
        interval_weeks: number;
        occurrence_count: number;
        timezone: string;
    };
    summary: {
        cadence: string;
        date_range: string;
    };
    occurrences: RecurringBookingOccurrence[];
    valid_occurrence_indexes: number[];
    conflict_count: number;
}

export interface RecurringBookingPreviewResponse {
    data: RecurringBookingPreviewData;
}

export interface RecurringBookingConfirmation {
    identifier: string;
    status: 'requested';
    status_label: 'Requested / Awaiting Management Approval';
    occurrence_count: number;
    requested_occurrence_count: number;
    first_date: string;
    last_date: string;
    timezone: string;
    occurrences: Array<{
        index: number;
        reference: string;
        starts_at: string;
        ends_at: string;
        price: BookingReviewQuote;
    }>;
}

export interface RecurringBookingSubmissionResponse {
    data: RecurringBookingConfirmation;
}
