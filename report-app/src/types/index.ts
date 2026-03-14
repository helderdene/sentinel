export interface IncidentType {
    id: number;
    name: string;
    category: string;
    code: string;
    default_priority: number;
    description: string;
}

export interface StoredReport {
    token: string;
    type: string;
    priority: number;
    barangay: string;
    status: string;
    submittedAt: string;
    description: string;
}

export interface CitizenReport {
    tracking_token: string;
    type: string;
    category: string;
    priority: number;
    status: string;
    barangay: string | null;
    location_text: string | null;
    description: string;
    submitted_at: string;
}

export interface Barangay {
    id: number;
    name: string;
}

export const CITIZEN_STATUS_MAP: Record<string, string> = {
    PENDING: 'Received',
    TRIAGED: 'Verified',
    DISPATCHED: 'Dispatched',
    ACKNOWLEDGED: 'Dispatched',
    EN_ROUTE: 'Dispatched',
    ON_SCENE: 'Dispatched',
    RESOLVING: 'Dispatched',
    RESOLVED: 'Resolved',
};

export const STATUS_SEQUENCE = [
    'Received',
    'Verified',
    'Dispatched',
    'Resolved',
] as const;

export const STATUS_COLORS: Record<string, string> = {
    Received: '#378ADD',
    Verified: '#7c3aed',
    Dispatched: '#EF9F27',
    Resolved: '#1D9E75',
};

export const PRIORITY_COLORS: Record<number, string> = {
    1: '#E24B4A',
    2: '#EF9F27',
    3: '#1D9E75',
    4: '#378ADD',
};

export const PRIORITY_LABELS: Record<number, string> = {
    1: 'CRITICAL',
    2: 'HIGH',
    3: 'MEDIUM',
    4: 'LOW',
};

export const PRIORITY_BG: Record<number, string> = {
    1: 'rgba(220,38,38,.08)',
    2: 'rgba(234,88,12,.08)',
    3: 'rgba(202,138,4,.08)',
    4: 'rgba(22,163,74,.08)',
};
