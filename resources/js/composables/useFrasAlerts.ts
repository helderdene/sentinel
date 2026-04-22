import { useEcho } from '@laravel/echo-vue';
import type { RecognitionAlertPayload } from '@/types/fras';

/**
 * Subscribes to the fras.alerts private channel and drives the dispatch
 * map pulse animation.
 *
 * Does NOT modify useDispatchFeed (INTEGRATION-04 gate) — Recognition-born
 * Incidents flow to the feed through the existing IncidentCreated broadcast.
 *
 * The caller provides a pulseCamera function (typically from useDispatchMap),
 * keeping channel subscription concerns here and visual state-machine concerns
 * in the map composable.
 *
 * Backend emits only Critical + Warning severities; Info events are filtered
 * at the factory (D-16) so no defensive severity check is needed beyond
 * matching the narrowed broadcast union.
 */
export function useFrasAlerts(
    pulseCamera: (cameraId: string, severity: 'critical' | 'warning') => void,
): void {
    useEcho<RecognitionAlertPayload>(
        'fras.alerts',
        'RecognitionAlertReceived',
        (payload) => {
            if (
                payload.severity === 'critical' ||
                payload.severity === 'warning'
            ) {
                pulseCamera(payload.camera_id, payload.severity);
            }
        },
    );
}
