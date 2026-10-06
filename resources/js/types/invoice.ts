export interface InvoiceSummary {
    id: number;
    reference: string;
    owner_type: 'individual' | 'organisation';
    owner_name: string;
    issue_date: string;
    due_date: string;
    status: 'issued' | 'paid';
    status_label: string;
    currency: string;
    total_minor: number;
    outstanding_minor: number;
    overdue: boolean;
}

export interface InvoiceDetail extends InvoiceSummary {
    lines: {
        id: number;
        description: string;
        amount_minor: number;
        booking_reference: string;
    }[];
}
