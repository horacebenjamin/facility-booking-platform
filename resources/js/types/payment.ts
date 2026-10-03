export interface PaymentBooking {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    financial_status: string;
    financial_status_label: string;
    resource_name: string;
    starts_at: string;
    ends_at: string;
    payment_due_at: string | null;
    amount_minor: number | null;
    currency: string | null;
    can_pay: boolean;
    unavailable_reason: string | null;
}

export interface PaymentSummary {
    status: string;
    requires_review: boolean;
}
