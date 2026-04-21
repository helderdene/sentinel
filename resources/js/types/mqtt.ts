export type MqttListenerHealthStatus =
    | 'HEALTHY'
    | 'SILENT'
    | 'DISCONNECTED'
    | 'NO_ACTIVE_CAMERAS';

export interface MqttListenerHealth {
    status: MqttListenerHealthStatus;
    lastMessageReceivedAt: string | null;
    since: string | null;
    activeCameraCount: number;
}

// Laravel broadcast payload uses snake_case keys
export interface MqttListenerHealthPayload {
    status: MqttListenerHealthStatus;
    last_message_received_at: string | null;
    since: string;
    active_camera_count: number;
}
