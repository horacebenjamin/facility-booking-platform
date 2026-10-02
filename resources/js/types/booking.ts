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
