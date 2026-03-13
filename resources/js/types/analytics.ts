export interface KpiMetrics {
    avg_response_time_min: number | null;
    avg_scene_arrival_time_min: number | null;
    resolution_rate: number;
    unit_utilization: number;
    false_alarm_rate: number;
}

export interface KpiTimeSeriesPoint {
    date: string;
    value: number;
}

export type KpiTimeSeries = Record<string, KpiTimeSeriesPoint[]>;

export interface BarangayDensity {
    barangay_id: number;
    name: string;
    incident_count: number;
}

export interface BarangayDetail {
    name: string;
    total: number;
    top_types: Array<{ name: string; count: number }>;
    priority_breakdown: Record<string, number>;
}

export interface AnalyticsFilters {
    preset: string | null;
    start_date: string | null;
    end_date: string | null;
    incident_type_id: number | null;
    priority: string | null;
    barangay_id: number | null;
}

export interface FilterOptions {
    incident_types: Array<{ id: number; name: string }>;
    barangays: Array<{ id: number; name: string }>;
}

export interface GeneratedReport {
    id: number;
    type: string;
    title: string;
    period: string;
    file_path: string;
    csv_path: string | null;
    status: string;
    created_at: string;
}
