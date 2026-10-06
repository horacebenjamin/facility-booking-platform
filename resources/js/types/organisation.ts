export type OrganisationRoleValue =
    | 'owner'
    | 'admin'
    | 'booking_manager'
    | 'finance'
    | 'member';

export interface OrganisationSummary {
    id: number;
    name: string;
    role: OrganisationRoleValue;
    role_label: string;
}

export interface OrganisationDetail extends OrganisationSummary {
    can_manage_members: boolean;
    can_create_bookings: boolean;
    can_view_bookings: boolean;
    can_view_finance: boolean;
}

export interface OrganisationMember {
    id: number;
    user_id: number;
    name: string;
    email: string;
    role: OrganisationRoleValue;
    role_label: string;
    joined_at: string;
}

export interface OrganisationRoleOption {
    value: OrganisationRoleValue;
    label: string;
}
