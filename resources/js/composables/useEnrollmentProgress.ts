import { useEcho } from '@laravel/echo-vue';
import type { Ref } from 'vue';
import { ref } from 'vue';

export type EnrollmentStatus = 'pending' | 'syncing' | 'done' | 'failed';

export type EnrollmentRow = {
    camera_id: string;
    camera_id_display: string | null;
    camera_name: string | null;
    status: EnrollmentStatus | null;
    last_error: string | null;
    enrolled_at: string | null;
};

type EnrollmentProgressedPayload = {
    personnel_id: string;
    camera_id: string;
    camera_id_display: string | null;
    status: EnrollmentStatus;
    last_error: string | null;
};

export function useEnrollmentProgress(
    personnelId: string,
    initialRows: EnrollmentRow[],
): { rows: Ref<Map<string, EnrollmentRow>> } {
    const rows = ref(
        new Map(initialRows.map((r) => [r.camera_id, r])),
    ) as Ref<Map<string, EnrollmentRow>>;

    useEcho<EnrollmentProgressedPayload>(
        'fras.enrollments',
        'EnrollmentProgressed',
        (e) => {
            if (e.personnel_id !== personnelId) {
                return;
            }

            const existing = rows.value.get(e.camera_id);
            const updated: EnrollmentRow = {
                camera_id: e.camera_id,
                camera_id_display:
                    e.camera_id_display ??
                    existing?.camera_id_display ??
                    null,
                camera_name: existing?.camera_name ?? null,
                status: e.status,
                last_error: e.last_error,
                enrolled_at:
                    e.status === 'done'
                        ? new Date().toISOString()
                        : (existing?.enrolled_at ?? null),
            };

            // Reactive Map replacement idiom (Phase 12 precedent).
            rows.value = new Map(rows.value).set(e.camera_id, updated);
        },
    );

    return { rows };
}
