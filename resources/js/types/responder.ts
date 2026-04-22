import type {
    IncidentPriority,
    IncidentStatus,
    IncidentTimelineEntry,
} from '@/types/incident';

export interface ResponderIncident {
    id: number;
    incident_no: string;
    priority: IncidentPriority;
    status: IncidentStatus;
    incident_type: {
        id: number;
        name: string;
        code: string;
        category: string;
    };
    location_text: string | null;
    barangay: { id: number; name: string } | null;
    coordinates: { lat: number; lng: number } | null;
    notes: string | null;
    caller_name: string | null;
    caller_contact: string | null;
    assigned_units: Array<{ id: string; callsign: string; type: string }>;
    vitals: VitalsData | null;
    assessment_tags: string[];
    checklist_data: Record<string, boolean> | null;
    checklist_pct: number;
    outcome: string | null;
    hospital: string | null;
    timeline: IncidentTimelineEntry[];
    acknowledged_at: string | null;
    en_route_at: string | null;
    on_scene_at: string | null;
    resolving_at: string | null;
    resolved_at: string | null;
    person_of_interest: PersonOfInterestContext | null;
}

export interface PersonOfInterestContext {
    face_image_url: string | null;
    personnel_name: string | null;
    personnel_category: string | null;
    camera_label: string | null;
    camera_name: string | null;
    captured_at: string | null;
}

export interface ResponderUnit {
    id: string;
    callsign: string;
    type: string;
    status: string;
    coordinates: { lat: number; lng: number } | null;
}

export interface VitalsData {
    systolic_bp: number | null;
    diastolic_bp: number | null;
    heart_rate: number | null;
    spo2: number | null;
    gcs: number | null;
}

export interface ChecklistTemplate {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    items: Array<{ key: string; label: string }>;
    is_default: boolean;
    is_active: boolean;
}

export type ResponderTab = 'assignment' | 'nav' | 'scene' | 'chat';

export interface AssignmentPayload {
    id: number;
    incident_no: string;
    priority: IncidentPriority;
    status: IncidentStatus;
    incident_type: string | null;
    location_text: string | null;
    barangay: string | null;
    coordinates: { lat: number; lng: number } | null;
    notes: string | null;
    unit_id: string;
}

export interface MessagePayload {
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

export interface IncidentMessageItem {
    id: number;
    body: string;
    is_quick_reply: boolean;
    sender: {
        id: number;
        name: string;
        role: string;
        unit_callsign: string | null;
    } | null;
    sender_type: string;
    sender_id: number;
    created_at: string;
}

export type IncidentOutcome =
    | 'TREATED_ON_SCENE'
    | 'TRANSPORTED_TO_HOSPITAL'
    | 'REFUSED_TREATMENT'
    | 'DECLARED_DOA'
    | 'FALSE_ALARM';

export type ResourceType =
    | 'ADDITIONAL_AMBULANCE'
    | 'FIRE_UNIT'
    | 'POLICE_BACKUP'
    | 'RESCUE_BOAT'
    | 'MEDICAL_OFFICER'
    | 'MEDEVAC';

export interface Hospital {
    id: string;
    name: string;
}

export type { IncidentPriority, IncidentStatus };
