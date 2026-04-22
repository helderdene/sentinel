/**
 * Types for FRAS recognition surfaces (Phase 21).
 * Consumed by useFrasAlerts, useFrasRail, and rail/modal components.
 */

export type FrasSeverity = 'critical' | 'warning' | 'info';
export type FrasBroadcastSeverity = 'critical' | 'warning';
export type PersonnelCategoryValue =
    | 'block'
    | 'missing'
    | 'lost_child'
    | 'allow';

/**
 * Payload shape for RecognitionAlertReceived broadcasts on fras.alerts.
 * Backend source: App\Events\RecognitionAlertReceived::broadcastWith().
 */
export interface RecognitionAlertPayload {
    event_id: string;
    camera_id: string;
    camera_id_display: string | null;
    camera_location: [number, number] | null;
    severity: FrasBroadcastSeverity;
    personnel_id: string | null;
    personnel_name: string | null;
    personnel_category: PersonnelCategoryValue | null;
    confidence: number;
    captured_at: string;
    incident_id: string | null;
}

/**
 * Event shape for the FRAS rail ring buffer.
 * Backend source: IntakeStationController::show()::$recentFrasEvents.
 */
export interface FrasRailEvent {
    event_id: string;
    severity: FrasBroadcastSeverity;
    camera_label: string | null;
    personnel_name: string | null;
    personnel_category: PersonnelCategoryValue | null;
    confidence: number;
    captured_at: string;
    incident_id: string | null;
    face_image_path: string | null;
    face_image_url: string | null;
}

/**
 * Inertia shared prop shape (HandleInertiaRequests::share).
 */
export interface FrasConfig {
    pulseDurationSeconds: number;
}
