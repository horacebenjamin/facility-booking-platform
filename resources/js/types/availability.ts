export interface Centre {
    id: number;
    name: string;
}

export interface Facility {
    id: number;
    centre_id: number;
    name: string;
}

export interface Resource {
    id: number;
    facility_id: number;
    name: string;
}

export interface Equipment {
    id: number;
    centre_id: number;
    facility_id: number | null;
    name: string;
    quantity: number;
}

export type AvailabilityReason =
    | 'invalid_period'
    | 'inactive_centre'
    | 'inactive_facility'
    | 'inactive_resource'
    | 'outside_bookable_hours'
    | 'resource_conflict'
    | 'blockout'
    | 'equipment_unavailable';

export interface AvailabilityResponse {
    data: {
        available: boolean;
        reasons: AvailabilityReason[];
    };
}
