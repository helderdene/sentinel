import { useEcho } from '@laravel/echo-vue';
import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import type { FrasRailEvent, RecognitionAlertPayload } from '@/types/fras';

const MAX_FRAS_FEED_SIZE = 50;

/**
 * SSR-seeded + Echo-hydrated ring buffer for the IntakeStation FRAS rail (D-18).
 *
 * @param initialEvents - The recentFrasEvents Inertia prop (top 50 Critical + Warning
 *                        recognition events on page load, pre-signed with 5-min TTL URLs).
 * @returns reactive frasEvents array (most-recent first) + frasCount computed.
 *
 * Behavior:
 * - Initial hydration from the SSR prop (stable session start).
 * - On RecognitionAlertReceived: prepend new events, evict tail beyond 50.
 * - If event_id already exists in the buffer (SSR row updated by mid-session
 *   Incident creation), update its incident_id in place rather than duplicating.
 * - face_image_path / face_image_url are null on live events — the broadcast
 *   payload doesn't carry signed URLs; those only flow through the SSR prop
 *   snapshot (Phase 22 scope for live signing).
 */
export function useFrasRail(initialEvents: FrasRailEvent[]): {
    frasEvents: Ref<FrasRailEvent[]>;
    frasCount: ComputedRef<number>;
} {
    const frasEvents = ref<FrasRailEvent[]>([...initialEvents]);

    useEcho<RecognitionAlertPayload>(
        'fras.alerts',
        'RecognitionAlertReceived',
        (payload) => {
            const existingIdx = frasEvents.value.findIndex(
                (e) => e.event_id === payload.event_id,
            );

            if (existingIdx !== -1) {
                frasEvents.value[existingIdx] = {
                    ...frasEvents.value[existingIdx],
                    incident_id: payload.incident_id,
                };

                return;
            }

            frasEvents.value.unshift({
                event_id: payload.event_id,
                severity: payload.severity,
                camera_label: payload.camera_id_display,
                personnel_name: payload.personnel_name,
                personnel_category: payload.personnel_category,
                confidence: payload.confidence,
                captured_at: payload.captured_at,
                incident_id: payload.incident_id,
                face_image_path: null,
                face_image_url: null,
            });

            if (frasEvents.value.length > MAX_FRAS_FEED_SIZE) {
                frasEvents.value.length = MAX_FRAS_FEED_SIZE;
            }
        },
    );

    const frasCount = computed(() => frasEvents.value.length);

    return { frasEvents, frasCount };
}
