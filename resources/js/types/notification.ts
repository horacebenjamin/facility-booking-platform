export interface CustomerNotification {
    id: string;
    type: string;
    title: string;
    body: string;
    action_label: string | null;
    action_url: string | null;
    occurred_at: string | null;
    created_at: string | null;
    read_at: string | null;
}

export interface NotificationPage {
    data: CustomerNotification[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
