import { usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import type { Ref } from 'vue';
import { ref } from 'vue';
import { useAlertSystem } from '@/composables/useAlertSystem';
import type {
    FrasAckPayload,
    FrasAlertItem,
    RecognitionAlertPayload,
} from '@/types/fras';

const MAX_ALERTS = 100;

/**
 * Map a RecognitionAlertReceived broadcast payload to the display-shaped
 * ring buffer item. Falls back to safe defaults when nullable personnel
 * fields are absent so TypeScript's strict shape is preserved.
 */
function mapPayloadToAlert(
    payload: RecognitionAlertPayload,
): FrasAlertItem {
    const category: 'block' | 'missing' | 'lost_child' =
        payload.personnel_category === 'block' ||
        payload.personnel_category === 'missing' ||
        payload.personnel_category === 'lost_child'
            ? payload.personnel_category
            : 'block';

    return {
        event_id: payload.event_id,
        severity: payload.severity,
        personnel: {
            id: payload.personnel_id ?? '',
            name: payload.personnel_name ?? 'Unknown',
            category,
        },
        camera: {
            id: payload.camera_id,
            camera_id_display:
                payload.camera_id_display ?? payload.camera_id,
            name: payload.camera_id_display ?? payload.camera_id,
        },
        captured_at: payload.captured_at,
        face_image_url: null,
        can_promote: payload.severity !== 'critical',
    };
}

/**
 * Subscribes to the fras.alerts private channel and exposes a reactive
 * ring-buffer of recent Critical + Warning recognition alerts.
 *
 * - New RecognitionAlertReceived events are unshifted onto `alerts` and
 *   truncated at MAX_ALERTS (100).
 * - FrasAlertAcknowledged events (from any operator) remove the matching
 *   card from the buffer — cross-operator feed reconciliation.
 * - Critical severity plays the P1 priority tone via useAlertSystem,
 *   gated by document.visibilityState === 'visible' AND the user's
 *   persisted `fras_audio_muted` preference (defense in depth: tab must
 *   be visible AND user must not have muted).
 *
 * Sibling of Phase 21's `useFrasAlerts.ts` — that file is not modified.
 */
export function useFrasFeed(initialAlerts: FrasAlertItem[] = []) {
    const alerts: Ref<FrasAlertItem[]> = ref(
        [...initialAlerts].slice(0, MAX_ALERTS),
    );
    const page = usePage();
    const { playPriorityTone } = useAlertSystem();

    useEcho<RecognitionAlertPayload>(
        'fras.alerts',
        'RecognitionAlertReceived',
        (payload) => {
            if (
                payload.severity !== 'critical' &&
                payload.severity !== 'warning'
            ) {
                return;
            }

            alerts.value.unshift(mapPayloadToAlert(payload));

            if (alerts.value.length > MAX_ALERTS) {
                alerts.value.length = MAX_ALERTS;
            }

            const auth = (
                page.props.auth as
                    | { user?: { fras_audio_muted?: boolean } }
                    | undefined
            );

            if (
                payload.severity === 'critical' &&
                document.visibilityState === 'visible' &&
                !auth?.user?.fras_audio_muted
            ) {
                playPriorityTone('P1');
            }
        },
    );

    useEcho<FrasAckPayload>(
        'fras.alerts',
        'FrasAlertAcknowledged',
        (payload) => {
            alerts.value = alerts.value.filter(
                (a) => a.event_id !== payload.event_id,
            );
        },
    );

    return { alerts };
}
