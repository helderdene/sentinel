<script setup lang="ts">
import { computed } from 'vue';
import PriBadge from '@/components/intake/PriBadge.vue';
import type { ResponderIncident } from '@/types/responder';

const props = defineProps<{
    incident: ResponderIncident;
}>();

const emit = defineEmits<{
    'show-resource-modal': [];
}>();

const priorityNumber = computed(() => {
    const map: Record<string, 1 | 2 | 3 | 4> = {
        P1: 1,
        P2: 2,
        P3: 3,
        P4: 4,
    };

    return map[props.incident.priority] ?? 4;
});

const STATUS_LABELS: Record<string, string> = {
    REPORTED: 'Reported',
    TRIAGED: 'Triaged',
    DISPATCHED: 'Dispatched',
    ACKNOWLEDGED: 'Acknowledged',
    EN_ROUTE: 'En Route',
    ON_SCENE: 'On Scene',
    RESOLVING: 'Resolving',
    RESOLVED: 'Resolved',
};

const STATUS_PIPELINE = [
    'DISPATCHED',
    'ACKNOWLEDGED',
    'EN_ROUTE',
    'ON_SCENE',
    'RESOLVING',
    'RESOLVED',
];

const currentStatusIndex = computed(() =>
    STATUS_PIPELINE.indexOf(props.incident.status),
);

const recentTimeline = computed(() =>
    props.incident.timeline.slice(-10).reverse(),
);

function formatTimelineDate(dateStr: string): string {
    const d = new Date(dateStr);

    return d.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    });
}
</script>

<template>
    <div class="hide-scrollbar flex flex-1 flex-col overflow-y-auto p-4">
        <div
            class="rounded-xl border border-t-border bg-t-surface shadow-[0_1px_4px_rgba(0,0,0,.04)]"
        >
            <div class="border-b border-t-border p-4">
                <div class="flex items-center gap-3">
                    <span
                        class="font-mono text-[11px] tracking-[1.5px] text-t-text-dim"
                    >
                        {{ incident.incident_no }}
                    </span>
                    <PriBadge :p="priorityNumber" />
                </div>
                <h2 class="mt-1 text-[16px] font-bold text-t-text">
                    {{ incident.incident_type.name }}
                </h2>
            </div>

            <div class="border-b border-t-border p-4">
                <div class="flex gap-1 overflow-x-auto">
                    <div
                        v-for="(status, index) in STATUS_PIPELINE"
                        :key="status"
                        class="flex items-center gap-1"
                    >
                        <span
                            class="rounded-full px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap"
                            :class="
                                index <= currentStatusIndex
                                    ? 'bg-t-accent/15 text-t-accent'
                                    : 'bg-t-border/50 text-t-text-faint'
                            "
                        >
                            {{ STATUS_LABELS[status] ?? status }}
                        </span>
                        <span
                            v-if="index < STATUS_PIPELINE.length - 1"
                            class="text-t-text-faint"
                        >
                            &rsaquo;
                        </span>
                    </div>
                </div>
            </div>

            <div class="space-y-3 p-4">
                <div v-if="incident.location_text">
                    <p
                        class="font-mono text-[10px] tracking-[1.5px] text-t-text-faint uppercase"
                    >
                        Location
                    </p>
                    <p class="text-[13px] text-t-text">
                        {{ incident.location_text }}
                    </p>
                    <p
                        v-if="incident.barangay"
                        class="text-[13px] text-t-text-dim"
                    >
                        Brgy. {{ incident.barangay.name }}
                    </p>
                </div>

                <div v-if="incident.caller_name || incident.caller_contact">
                    <p
                        class="font-mono text-[10px] tracking-[1.5px] text-t-text-faint uppercase"
                    >
                        Caller
                    </p>
                    <p
                        v-if="incident.caller_name"
                        class="text-[13px] text-t-text"
                    >
                        {{ incident.caller_name }}
                    </p>
                    <p
                        v-if="incident.caller_contact"
                        class="font-mono text-[11px] text-t-text-dim"
                    >
                        {{ incident.caller_contact }}
                    </p>
                </div>

                <div v-if="incident.notes">
                    <p
                        class="font-mono text-[10px] tracking-[1.5px] text-t-text-faint uppercase"
                    >
                        Notes
                    </p>
                    <p class="text-[13px] text-t-text-mid">
                        {{ incident.notes }}
                    </p>
                </div>

                <div v-if="incident.assigned_units?.length > 0">
                    <p
                        class="font-mono text-[10px] tracking-[1.5px] text-t-text-faint uppercase"
                    >
                        Assigned Units
                    </p>
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        <span
                            v-for="unit in incident.assigned_units"
                            :key="unit.id"
                            class="inline-flex items-center rounded-full bg-t-accent/10 px-2.5 py-0.5 font-mono text-[11px] font-semibold text-t-accent"
                        >
                            {{ unit.callsign }}
                        </span>
                    </div>
                </div>

                <div v-if="recentTimeline.length > 0">
                    <p
                        class="font-mono text-[10px] tracking-[1.5px] text-t-text-faint uppercase"
                    >
                        Timeline
                    </p>
                    <div class="mt-1 space-y-1.5">
                        <div
                            v-for="entry in recentTimeline"
                            :key="entry.id"
                            class="flex items-start gap-2"
                        >
                            <span
                                class="mt-0.5 size-1.5 shrink-0 rounded-full bg-t-text-faint"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[11px] text-t-text-mid">
                                    {{ entry.event_type.replace(/_/g, ' ') }}
                                </p>
                            </div>
                            <span
                                class="shrink-0 font-mono text-[10px] text-t-text-faint"
                            >
                                {{ formatTimelineDate(entry.created_at) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button
            type="button"
            class="mt-4 flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[10px] border border-t-border bg-t-surface font-sans text-[13px] font-semibold text-t-text shadow-[0_1px_3px_rgba(0,0,0,.04)] transition-colors active:bg-t-border/30"
            @click="emit('show-resource-modal')"
        >
            <svg
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="text-t-text-dim"
            >
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="16" />
                <line x1="8" y1="12" x2="16" y2="12" />
            </svg>
            Request Resource
        </button>
    </div>
</template>
