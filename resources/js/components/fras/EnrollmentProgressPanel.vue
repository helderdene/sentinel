<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    resyncAll,
    retry,
} from '@/actions/App/Http/Controllers/Admin/EnrollmentController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type {
    EnrollmentRow,
    EnrollmentStatus,
} from '@/composables/useEnrollmentProgress';
import { useEnrollmentProgress } from '@/composables/useEnrollmentProgress';

const props = defineProps<{
    personnelId: string;
    initialRows: EnrollmentRow[];
}>();

const { rows } = useEnrollmentProgress(props.personnelId, props.initialRows);

const processing = ref(false);

const rowList = computed(() =>
    Array.from(rows.value.values()).sort((a, b) =>
        (a.camera_id_display ?? '').localeCompare(b.camera_id_display ?? ''),
    ),
);

const allDone = computed(
    () =>
        rowList.value.length > 0 &&
        rowList.value.every((r) => r.status === 'done'),
);

function onRetry(cameraId: string): void {
    router.post(
        retry({ personnel: props.personnelId, camera: cameraId }).url,
        {},
        { preserveScroll: true },
    );

    // Optimistic local transition to Pending.
    const existing = rows.value.get(cameraId);

    if (existing) {
        rows.value = new Map(rows.value).set(cameraId, {
            ...existing,
            status: 'pending',
            last_error: null,
        });
    }
}

function onResyncAll(): void {
    processing.value = true;
    router.post(
        resyncAll(props.personnelId).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );

    // Optimistic: mark every row pending until Echo overwrites.
    const next = new Map<string, EnrollmentRow>();

    for (const [id, row] of rows.value) {
        next.set(id, { ...row, status: 'pending', last_error: null });
    }

    rows.value = next;
}

function statusClass(s: EnrollmentStatus | null): string {
    switch (s) {
        case 'pending':
            return 'bg-[color-mix(in_srgb,var(--t-text-faint)_12%,transparent)] text-t-text-faint';

        case 'syncing':
            return 'bg-[color-mix(in_srgb,var(--t-accent)_12%,transparent)] text-t-accent';

        case 'done':
            return 'bg-[color-mix(in_srgb,var(--t-online)_12%,transparent)] text-t-online';

        case 'failed':
            return 'bg-[color-mix(in_srgb,var(--t-p1)_12%,transparent)] text-t-p1';
    }

    return 'bg-[color-mix(in_srgb,var(--t-text-faint)_12%,transparent)] text-t-text-faint';
}

function statusLabel(s: EnrollmentStatus | null): string {
    switch (s) {
        case 'pending':
            return 'Pending';

        case 'syncing':
            return 'Syncing';

        case 'done':
            return 'Done';

        case 'failed':
            return 'Failed';
    }

    return '—';
}
</script>

<template>
    <div
        class="rounded-[var(--radius)] border border-border bg-card shadow-[var(--shadow-1)]"
    >
        <div
            class="flex items-center justify-between border-b border-border px-4 py-3"
        >
            <h4
                class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
            >
                Enrollment Status
            </h4>
            <Button
                variant="outline"
                size="sm"
                :disabled="processing"
                @click="onResyncAll"
            >
                Resync all cameras
            </Button>
        </div>

        <div
            v-if="rowList.length === 0"
            class="p-4 text-center text-[13px] text-t-text-faint"
        >
            No active cameras. Add a camera to begin enrollment.
        </div>

        <div
            v-else-if="allDone"
            class="flex items-center justify-center gap-1 border-b border-border bg-[color-mix(in_srgb,var(--t-online)_8%,transparent)] px-4 py-2 text-[13px] text-t-online"
        >
            <span aria-hidden="true">●</span>
            All cameras synced. Enrollment complete.
        </div>

        <div
            role="status"
            aria-live="polite"
            aria-atomic="false"
            class="divide-y divide-border"
        >
            <div
                v-for="row in rowList"
                :key="row.camera_id"
                class="flex items-center justify-between gap-4 px-4 py-3"
                :aria-busy="row.status === 'syncing'"
            >
                <div class="min-w-0 flex-1">
                    <div
                        class="font-mono text-[10px] text-t-text-faint"
                    >
                        {{ row.camera_id_display ?? row.camera_id }}
                    </div>
                    <div class="truncate text-sm text-foreground">
                        {{ row.camera_name ?? '—' }}
                    </div>
                    <div
                        v-if="row.status === 'failed'"
                        class="mt-1 text-xs text-t-p1"
                    >
                        {{
                            row.last_error ||
                            'Enrollment failed. Click "Retry this camera" to try again.'
                        }}
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    <Badge
                        variant="secondary"
                        :class="statusClass(row.status)"
                    >
                        <span
                            v-if="row.status === 'syncing'"
                            aria-hidden="true"
                            class="mr-1 animate-pulse"
                        >●</span>
                        {{ statusLabel(row.status) }}
                    </Badge>

                    <Button
                        v-if="row.status === 'failed'"
                        variant="ghost"
                        size="sm"
                        class="text-t-accent hover:text-t-accent"
                        @click="onRetry(row.camera_id)"
                    >
                        Retry this camera
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
