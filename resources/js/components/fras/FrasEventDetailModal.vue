<script setup lang="ts">
import { computed } from 'vue';
import type { RecognitionEventRow } from '@/components/fras/EventHistoryTable.vue';
import FrasSeverityBadge from '@/components/fras/FrasSeverityBadge.vue';
import ImagePurgedPlaceholder from '@/components/fras/ImagePurgedPlaceholder.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { PersonnelCategoryValue } from '@/types/fras';

/**
 * Extended event shape for the shared Phase 22 detail modal. Augments the
 * paginator row with optional fields the Inertia controller may hydrate.
 */
export interface FrasDetailEvent extends RecognitionEventRow {
    event_id?: string | null;
    received_at?: string | null;
    confidence?: number | null;
    scene_image_url?: string | null | undefined;
}

const props = defineProps<{
    event: FrasDetailEvent | null;
    /** ISO-8601 UTC string marking when this viewing access was logged. */
    accessLogTimestamp?: string | null;
}>();

const emit = defineEmits<{
    close: [];
    promote: [];
}>();

const CATEGORY_LABELS: Record<PersonnelCategoryValue, string> = {
    block: 'Block-list match',
    missing: 'Missing person sighting',
    lost_child: 'Lost child sighting',
    allow: 'Allow-listed',
};

const DISMISS_LABELS: Record<string, string> = {
    false_match: 'False match',
    test_event: 'Test event',
    duplicate: 'Duplicate',
    other: 'Other',
};

const openModel = computed({
    get: () => props.event !== null,
    set: (v: boolean) => {
        if (!v) {
            emit('close');
        }
    },
});

const categoryLabel = computed(() => {
    const ev = props.event;

    if (!ev || !ev.personnel?.category) {
        return 'Unknown';
    }

    return CATEGORY_LABELS[ev.personnel.category];
});

const whyNoIncident = computed<string | null>(() => {
    const ev = props.event;

    if (!ev || ev.incident_id !== null) {
        return null;
    }

    if (ev.severity === 'warning') {
        return 'Warning severity — operator awareness only. This event did not meet the threshold to create an incident automatically.';
    }

    if (ev.severity === 'info') {
        return 'Info severity — recorded for audit only. Info events never auto-create incidents.';
    }

    if (ev.personnel?.category === 'allow') {
        return 'Person is on the allow-list — no incident is created for visitor passes. Event recorded for audit only.';
    }

    if (!ev.personnel) {
        return 'No matched personnel record. Event recorded without identification.';
    }

    if (typeof ev.confidence === 'number' && ev.confidence < 0.75) {
        return `Match confidence below threshold (${(ev.confidence * 100).toFixed(1)}% vs. 75% required). Review manually before escalating.`;
    }

    return 'Suppressed by dedup window — a recent event for this camera and personnel already created an incident.';
});

const canPromote = computed(() => {
    const ev = props.event;

    if (!ev) {
        return false;
    }

    return (
        ev.incident_id === null &&
        ev.severity !== 'critical' &&
        ev.can_promote
    );
});

const confidencePct = computed(() => {
    const c = props.event?.confidence;

    if (typeof c !== 'number') {
        return '—';
    }

    return `${(c * 100).toFixed(1)}%`;
});

const hasSceneField = computed(() => {
    // Responder role: backend omits scene_image_url entirely (key absent).
    // Detect via property existence (undefined === not hydrated / stripped).
    return (
        props.event !== null &&
        Object.prototype.hasOwnProperty.call(props.event, 'scene_image_url')
    );
});

const accessTimestampDisplay = computed(() => {
    const ts = props.accessLogTimestamp;

    if (!ts) {
        // Fall back to current time when no explicit access log supplied — the
        // signed image fetch itself writes the authoritative audit row.
        return new Date().toISOString();
    }

    return ts;
});

function formatIso(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    return iso;
}
</script>

<template>
    <Dialog v-model:open="openModel">
        <DialogContent
            class="max-h-[calc(100vh-8rem)] max-w-2xl space-y-6 overflow-y-auto p-6"
        >
            <DialogHeader>
                <DialogTitle>Recognition Event Details</DialogTitle>
                <!-- prettier-ignore -->
                <DialogDescription>Full recognition event. Image access is logged per DPA policy.</DialogDescription>
            </DialogHeader>

            <template v-if="event">
                <!-- Header strip -->
                <div
                    class="flex items-center gap-4 rounded-[var(--radius)] border border-t-border bg-t-bg/30 p-3"
                >
                    <div
                        class="size-12 flex-shrink-0 overflow-hidden rounded-[var(--radius)] bg-t-bg"
                    >
                        <img
                            v-if="event.face_image_url"
                            :src="event.face_image_url"
                            :alt="`Face of ${event.personnel?.name ?? 'unknown person'}`"
                            class="size-full object-cover"
                        />
                        <div
                            v-else
                            class="flex size-full items-center justify-center text-t-text-faint"
                            aria-hidden="true"
                        >
                            ●
                        </div>
                    </div>
                    <div class="flex-1">
                        <div class="text-sm font-semibold text-t-text">
                            {{ event.personnel?.name ?? 'Unknown person' }}
                        </div>
                        <div class="text-xs text-t-text-dim">
                            {{ categoryLabel }}
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <FrasSeverityBadge :severity="event.severity" />
                        <span
                            v-if="event.incident_id !== null"
                            class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 font-mono text-[10px] tracking-[1.5px] uppercase"
                            :style="{
                                backgroundColor:
                                    'color-mix(in srgb, var(--t-online) 15%, transparent)',
                                borderColor:
                                    'color-mix(in srgb, var(--t-online) 40%, transparent)',
                                color: 'var(--t-online)',
                            }"
                        >
                            <span aria-hidden="true">●</span>
                            CREATED INCIDENT
                        </span>
                    </div>
                </div>

                <!-- Why No Incident -->
                <section v-if="whyNoIncident" class="space-y-2">
                    <h3
                        class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                    >
                        Why No Incident
                    </h3>
                    <p class="text-xs text-t-text">
                        {{ whyNoIncident }}
                    </p>
                </section>

                <!-- Event Details -->
                <section class="space-y-2">
                    <h3
                        class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                    >
                        Event Details
                    </h3>
                    <dl
                        class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-xs"
                    >
                        <dt
                            class="font-mono text-[9px] text-t-text-faint uppercase"
                        >
                            Event ID
                        </dt>
                        <dd class="font-mono break-all text-t-text">
                            {{ event.event_id ?? event.id }}
                        </dd>
                        <dt
                            class="font-mono text-[9px] text-t-text-faint uppercase"
                        >
                            Camera
                        </dt>
                        <dd class="font-mono text-t-text">
                            {{ event.camera.camera_id_display }} —
                            {{ event.camera.name }}
                        </dd>
                        <dt
                            class="font-mono text-[9px] text-t-text-faint uppercase"
                        >
                            Captured at
                        </dt>
                        <dd class="font-mono text-t-text">
                            {{ event.captured_at }}
                        </dd>
                        <dt
                            v-if="event.received_at"
                            class="font-mono text-[9px] text-t-text-faint uppercase"
                        >
                            Received at
                        </dt>
                        <dd
                            v-if="event.received_at"
                            class="font-mono text-t-text"
                        >
                            {{ formatIso(event.received_at) }}
                        </dd>
                        <dt
                            class="font-mono text-[9px] text-t-text-faint uppercase"
                        >
                            Confidence
                        </dt>
                        <dd class="font-mono text-t-text">
                            {{ confidencePct }}
                        </dd>
                        <template v-if="event.personnel">
                            <dt
                                class="font-mono text-[9px] text-t-text-faint uppercase"
                            >
                                Personnel ID
                            </dt>
                            <dd class="font-mono break-all text-t-text">
                                {{ event.personnel.id }}
                            </dd>
                        </template>
                    </dl>
                </section>

                <!-- Face Capture -->
                <section class="space-y-2">
                    <h3
                        class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                    >
                        Face Capture
                    </h3>
                    <div
                        v-if="event.face_image_url"
                        class="size-48 overflow-hidden rounded-[var(--radius)] bg-t-bg"
                    >
                        <img
                            :src="event.face_image_url"
                            :alt="`Face capture at ${event.camera.name}`"
                            class="size-full object-cover"
                        />
                    </div>
                    <ImagePurgedPlaceholder v-else />
                </section>

                <!-- Scene: hidden entirely when backend strips the key (responder role) -->
                <section v-if="hasSceneField" class="space-y-2">
                    <h3
                        class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                    >
                        Scene
                    </h3>
                    <div
                        v-if="event.scene_image_url"
                        class="aspect-video w-full overflow-hidden rounded-[var(--radius)] bg-t-bg"
                    >
                        <img
                            :src="event.scene_image_url ?? ''"
                            :alt="`Scene capture at ${event.camera.name}`"
                            class="size-full object-cover"
                        />
                    </div>
                    <ImagePurgedPlaceholder v-else />
                </section>

                <!-- Access Log strip -->
                <section
                    class="space-y-2 rounded-[var(--radius)] border border-t-border bg-t-surface-alt/40 p-3"
                >
                    <h3
                        class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                    >
                        Access Log
                    </h3>
                    <p class="text-xs text-t-text-dim">
                        This view was logged at
                        <span class="font-mono">{{
                            accessTimestampDisplay
                        }}</span>
                        UTC. Image fetches are audited per DPA-02.
                    </p>
                    <p
                        v-if="event.acknowledged_at && event.acknowledged_by"
                        class="text-xs text-t-text-dim"
                    >
                        Acknowledged by
                        <span class="font-semibold text-t-text">{{
                            event.acknowledged_by.name
                        }}</span>
                        at
                        <span class="font-mono">{{
                            event.acknowledged_at
                        }}</span>
                    </p>
                    <p
                        v-if="event.dismissed_at && event.dismissed_by"
                        class="text-xs text-t-text-dim"
                    >
                        Dismissed by
                        <span class="font-semibold text-t-text">{{
                            event.dismissed_by.name
                        }}</span>
                        at
                        <span class="font-mono">{{ event.dismissed_at }}</span>
                        <span v-if="event.dismiss_reason">
                            — reason:
                            {{
                                DISMISS_LABELS[event.dismiss_reason] ??
                                event.dismiss_reason
                            }}
                        </span>
                    </p>
                </section>
            </template>

            <DialogFooter class="flex items-center justify-between">
                <DialogClose as-child>
                    <Button variant="outline" @click="emit('close')">
                        Close
                    </Button>
                </DialogClose>
                <Button v-if="canPromote" @click="emit('promote')">
                    Promote to Incident
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
