export type IncidentChannel = 'phone' | 'sms' | 'app' | 'iot' | 'radio';

export type IncidentPriority = 'P1' | 'P2' | 'P3' | 'P4';

export type IncidentStatus =
    | 'PENDING'
    | 'TRIAGED'
    | 'DISPATCHED'
    | 'ACKNOWLEDGED'
    | 'EN_ROUTE'
    | 'ON_SCENE'
    | 'RESOLVING'
    | 'RESOLVED';

export interface PrioritySuggestion {
    priority: IncidentPriority;
    confidence: number;
}

export interface GeocodingResult {
    lat: number;
    lng: number;
    display_name: string;
}

export interface IncidentType {
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
}

export interface Incident {
    id: string;
    incident_no: string;
    incident_type_id: number;
    incident_type?: IncidentType;
    priority: IncidentPriority;
    status: IncidentStatus;
    channel: IncidentChannel;
    location_text: string;
    coordinates: { lat: number; lng: number } | null;
    barangay_id: number | null;
    barangay?: { id: number; name: string } | null;
    caller_name: string | null;
    caller_contact: string | null;
    raw_message: string | null;
    notes: string | null;
    assigned_unit: string | null;
    dispatched_at: string | null;
    acknowledged_at: string | null;
    en_route_at: string | null;
    on_scene_at: string | null;
    resolved_at: string | null;
    outcome: string | null;
    hospital: string | null;
    scene_time_sec: number | null;
    checklist_pct: number | null;
    vitals: Record<string, unknown> | null;
    assessment_tags: string[] | null;
    closure_notes: string | null;
    report_pdf_url: string | null;
    created_by: number | null;
    created_by_user?: { id: number; name: string } | null;
    timeline?: IncidentTimelineEntry[];
    messages?: unknown[];
    created_at: string;
    updated_at: string;
    resource_requests?: ResourceRequest[];
}

export interface IncidentTimelineEntry {
    id: number;
    incident_id: string;
    event_type: string;
    event_data: Record<string, unknown> | null;
    actor_type: string | null;
    actor_id: number | null;
    actor?: { id: number; name: string } | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

export interface ResourceRequest {
    resource_type: string;
    resource_label: string;
    notes: string | null;
    requested_by: string;
    timestamp: string;
}

export interface IncidentForQueue {
    id: string;
    incident_no: string;
    incident_type: IncidentType;
    priority: IncidentPriority;
    status: IncidentStatus;
    channel: IncidentChannel;
    location_text: string;
    barangay: { id: number; name: string } | null;
    caller_name: string | null;
    created_at: string;
    resource_requests?: ResourceRequest[];
}

export interface IncidentCreatedPayload {
    id: string;
    incident_no: string;
    incident_type_id: number;
    priority: IncidentPriority;
    status: IncidentStatus;
    incident_type: string | null;
    location_text: string;
    barangay: string | null;
    channel: IncidentChannel;
    coordinates: { lat: number; lng: number } | null;
    caller_name: string | null;
    caller_contact: string | null;
    notes: string | null;
    created_at: string;
}

export interface TickerEvent {
    incident_no: string;
    priority: IncidentPriority;
    channel: IncidentChannel;
    incident_type: string | null;
    location_text: string;
    created_at: string;
}

export interface IncidentStatusChangedPayload {
    id: string;
    incident_no: string;
    old_status: IncidentStatus;
    new_status: IncidentStatus;
    priority: IncidentPriority;
}

export interface StateSyncResponse {
    incidents: IncidentForQueue[];
    channelCounts: Record<string, number>;
    units: Array<{
        id: string;
        callsign: string;
        type: string;
        status: string;
        coordinates: { lat: number; lng: number } | null;
    }>;
}

export interface ChecklistUpdatedPayload {
    incident_id: string;
    incident_no: string;
    checklist_pct: number;
}

export interface ResourceRequestedPayload {
    incident_id: string;
    incident_no: string;
    resource_type: string;
    resource_label: string;
    notes: string | null;
    requested_by: string;
    timestamp: string;
}
