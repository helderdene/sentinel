<script setup lang="ts">
import { computed } from 'vue';
import FrasSeverityBadge from '@/components/fras/FrasSeverityBadge.vue';
import IntakeIconFras from '@/components/intake/icons/IntakeIconFras.vue';
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
import type { FrasRailEvent, PersonnelCategoryValue } from '@/types/fras';

const props = defineProps<{
    open: boolean;
    event: FrasRailEvent | null;
}>();

const emit = defineEmits<{ 'update:open': [open: boolean] }>();

const openModel = computed({
    get: () => props.open,
    set: (v: boolean) => emit('update:open', v),
});

const CATEGORY_LABELS: Record<PersonnelCategoryValue, string> = {
    block: 'Block-list match',
    missing: 'Missing person sighting',
    lost_child: 'Lost child sighting',
    allow: 'Allow-listed',
};

const categoryLabel = computed(() => {
    const ev = props.event;

    if (!ev || !ev.personnel_category) {
        return 'Unknown';
    }

    return CATEGORY_LABELS[ev.personnel_category];
});

const whyNoIncident = computed<string | null>(() => {
    const ev = props.event;

    if (!ev || ev.incident_id !== null) {
        return null;
    }

    if (ev.severity === 'warning') {
        return 'Warning severity — operator awareness only. This event did not meet the threshold to create an incident automatically.';
    }

    if (ev.personnel_category === 'allow') {
        return 'Person is on the allow-list — no incident is created for visitor passes. Event recorded for audit only.';
    }

    if (!ev.personnel_name) {
        return 'No matched personnel record. Event recorded without identification.';
    }

    if (ev.confidence < 0.75) {
        return `Match confidence below threshold (${(ev.confidence * 100).toFixed(1)}% vs. 75% required). Review manually before escalating.`;
    }

    return 'Suppressed by dedup window — a recent event for this camera and personnel already created an incident.';
});

const confidencePct = computed(() =>
    props.event ? `${(props.event.confidence * 100).toFixed(1)}%` : '—',
);
</script>

<template>
    <Dialog v-model:open="openModel">
        <DialogContent class="max-w-2xl space-y-6 p-6">
            <DialogHeader>
                <DialogTitle>Recognition Event Details</DialogTitle>
                <DialogDescription>
                    Read-only view of a FRAS recognition event that did not
                    create an incident. Image retention follows DPA policy.
                </DialogDescription>
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
                            :alt="`Face of ${event.personnel_name ?? 'unknown person'}`"
                            class="size-full object-cover"
                        />
                        <div
                            v-else
                            class="flex size-full items-center justify-center"
                            style="color: var(--t-ch-fras)"
                            aria-hidden="true"
                        >
                            <IntakeIconFras :size="24" />
                        </div>
                    </div>
                    <div class="flex-1">
                        <div class="text-sm font-semibold text-t-text">
                            {{ event.personnel_name ?? 'Unknown person' }}
                        </div>
                        <div class="text-xs text-t-text-dim">
                            {{ categoryLabel }}
                        </div>
                    </div>
                    <FrasSeverityBadge :severity="event.severity" />
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
                            {{ event.event_id }}
                        </dd>
                        <dt
                            class="font-mono text-[9px] text-t-text-faint uppercase"
                        >
                            Camera
                        </dt>
                        <dd class="font-mono text-t-text">
                            {{ event.camera_label ?? '—' }}
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
                            class="font-mono text-[9px] text-t-text-faint uppercase"
                        >
                            Confidence
                        </dt>
                        <dd class="font-mono text-t-text">
                            {{ confidencePct }}
                        </dd>
                    </dl>
                </section>

                <!-- Face Capture -->
                <section v-if="event.face_image_url" class="space-y-2">
                    <h3
                        class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase"
                    >
                        Face Capture
                    </h3>
                    <div
                        class="size-48 overflow-hidden rounded-[var(--radius)] bg-t-bg"
                    >
                        <img
                            :src="event.face_image_url"
                            :alt="`Face capture at ${event.camera_label ?? 'unknown camera'}`"
                            class="size-full object-cover"
                        />
                    </div>
                </section>
            </template>

            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Close</Button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
