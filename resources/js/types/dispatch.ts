import type {
    Incident,
    IncidentPriority,
    IncidentStatus,
} from '@/types/incident';

export type UnitStatus =
    | 'AVAILABLE'
    | 'DISPATCHED'
    | 'EN_ROUTE'
    | 'ON_SCENE'
    | 'OFFLINE';

export interface AssignedUnitPivot {
    unit_id: string;
    callsign: string;
    assigned_at: string;
    acknowledged_at: string | null;
}

export interface DispatchIncident extends Incident {
    incident_type: {
        id: number;
        incident_category_id: number | null;
        category: string;
        name: string;
        code: string;
        default_priority: IncidentPriority;
        is_active: boolean;
        incident_category: {
            id: number;
            name: string;
            icon: string;
        } | null;
    };
    barangay: { id: number; name: string } | null;
    assigned_units: AssignedUnitPivot[];
}

export interface DispatchUnit {
    id: string;
    callsign: string;
    type: string;
    agency: string;
    crew_capacity: number;
    status: UnitStatus;
    coordinates: { lat: number; lng: number } | null;
    active_incident_id: string | null;
}

export interface DispatchAgency {
    id: number;
    name: string;
    code: string;
    contact_phone: string | null;
    contact_email: string | null;
    radio_channel: string | null;
    incident_types: Array<{ id: number; name: string; category: string }>;
}

export interface DispatchMetrics {
    totalIncidents: number;
    activeIncidents: number;
    criticalIncidents: number;
    unitsAvailable: number;
    unitsTotal: number;
    averageHandleTime: number | null;
}

export interface NearbyUnit {
    id: string;
    callsign: string;
    type: string;
    agency: string;
    crew_capacity: number;
    distance_km: number;
    eta_minutes: number;
}

export interface UnitLocationPayload {
    id: string;
    callsign: string;
    latitude: number;
    longitude: number;
}

export interface AssignmentResult {
    success: boolean;
}

export interface MutualAidPayload {
    incident_id: string;
    incident_no: string;
    agency: { name: string; code: string };
    notes: string;
    requested_by: string;
    timestamp: string;
}

export interface UnitStatusChangedPayload {
    id: string;
    callsign: string;
    old_status: UnitStatus;
    new_status: UnitStatus;
}

export interface DispatchMessagePayload {
    id: number;
    incident_id: string;
    sender_id: number;
    sender_name: string;
    sender_role: string;
    sender_unit_callsign: string | null;
    body: string;
    is_quick_reply: boolean;
    sent_at: string;
}

export interface DispatchMessageItem {
    id: number;
    body: string;
    is_quick_reply: boolean;
    sender_id: number;
    sender_name: string;
    sender_role: string;
    sender_unit_callsign: string | null;
    sent_at: string;
}

export type { IncidentPriority, IncidentStatus };
