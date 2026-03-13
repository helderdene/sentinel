<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { nearbyUnits as nearbyUnitsAction } from '@/actions/App/Http/Controllers/DispatchConsoleController';
import AckTimerRing from '@/components/dispatch/AckTimerRing.vue';
import AssignmentChip from '@/components/dispatch/AssignmentChip.vue';
import SlaProgressBar from '@/components/dispatch/SlaProgressBar.vue';
import StatusPipeline from '@/components/dispatch/StatusPipeline.vue';
import PriBadge from '@/components/intake/PriBadge.vue';
import type { DispatchIncident, NearbyUnit } from '@/types/dispatch';
import type { IncidentTimelineEntry } from '@/types/incident';

const props = defineProps<{
    incident: DispatchIncident;
}>();

const emit = defineEmits<{
    close: [];
    'ack-expired': [];
    unassign: [unitId: string];
}>();

const priorityNumber = computed(() => {
    const num = parseInt(props.incident.priority.replace('P', ''), 10);

    return (num >= 1 && num <= 4 ? num : 4) as 1 | 2 | 3 | 4;
});

const statusLabel = computed(() => props.incident.status.replace(/_/g, ' '));

const statusColorMap: Record<string, string> = {
    TRIAGED: 'var(--t-queued)',
    DISPATCHED: 'var(--t-unit-dispatched)',
    EN_ROUTE: 'var(--t-unit-enroute)',
    ON_SCENE: 'var(--t-unit-onscene)',
    RESOLVED: 'var(--t-p4)',
};

const statusColor = computed(
    () => statusColorMap[props.incident.status] ?? 'var(--t-text-faint)',
);

const nearbyUnitsList = ref<NearbyUnit[]>([]);
const isLoadingNearby = ref(false);

async function fetchNearbyUnits(): Promise<void> {
    isLoadingNearby.value = true;

    try {
        const url = nearbyUnitsAction.url(props.incident.id);
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.ok) {
            nearbyUnitsList.value = await response.json();
        }
    } finally {
        isLoadingNearby.value = false;
    }
}

watch(
    () => props.incident.id,
    () => {
        nearbyUnitsList.value = [];
        fetchNearbyUnits();
    },
    { immediate: true },
);

const elapsedText = computed(() => {
    const diff = Math.floor(
        (Date.now() - new Date(props.incident.created_at).getTime()) / 1000,
    );

    if (diff < 60) {
        return `${diff}s ago`;
    }

    if (diff < 3600) {
        return `${Math.floor(diff / 60)}m ago`;
    }

    return `${Math.floor(diff / 3600)}h ${Math.floor((diff % 3600) / 60)}m ago`;
});

const timelineEntries = computed<IncidentTimelineEntry[]>(
    () => props.incident.timeline ?? [],
);

function handleUnassign(unitId: string): void {
    if (confirm('Unassign this unit from the incident?')) {
        emit('unassign', unitId);
    }
}
</script>

<template>
    <div class="flex h-full flex-col overflow-y-auto">
        <!-- Header -->
        <div
            class="flex items-start justify-between border-b border-t-border px-3 py-2.5"
        >
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="font-mono text-sm font-bold text-t-text">
                        {{ incident.incident_no }}
                    </span>
                    <PriBadge :p="priorityNumber" size="sm" />
                    <span
                        class="rounded px-1.5 py-[1px] font-mono text-[9px] font-bold"
                        :style="{
                            backgroundColor: `color-mix(in srgb, ${statusColor} 12%, transparent)`,
                            color: statusColor,
                        }"
                    >
                        {{ statusLabel }}
                    </span>
                </div>
                <div class="mt-1 truncate text-xs font-semibold text-t-text">
                    {{ incident.incident_type?.name ?? 'Unclassified' }}
                </div>
            </div>
            <button
                class="ml-2 flex size-6 shrink-0 items-center justify-center rounded text-t-text-faint transition-colors hover:bg-t-surface-alt hover:text-t-text"
                @click="emit('close')"
            >
                <svg
                    class="size-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M6 18L18 6M6 6l12 12"
                    />
                </svg>
            </button>
        </div>

        <!-- SLA Progress -->
        <div class="border-b border-t-border px-3 py-2.5">
            <SlaProgressBar
                :priority="incident.priority"
                :created-at="incident.created_at"
            />
        </div>

        <!-- Info section -->
        <div class="space-y-1.5 border-b border-t-border px-3 py-2.5">
            <div class="flex items-start gap-2">
                <svg
                    class="mt-0.5 size-3 shrink-0 text-t-text-faint"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"
                    />
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"
                    />
                </svg>
                <div class="min-w-0">
                    <div class="text-xs text-t-text">
                        {{ incident.location_text }}
                    </div>
                    <div
                        v-if="incident.barangay"
                        class="text-[10px] text-t-text-dim"
                    >
                        Brgy. {{ incident.barangay.name }}
                    </div>
                </div>
            </div>

            <div
                v-if="incident.caller_name || incident.caller_contact"
                class="flex items-center gap-2"
            >
                <svg
                    class="size-3 shrink-0 text-t-text-faint"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"
                    />
                </svg>
                <div class="text-xs text-t-text-dim">
                    <span v-if="incident.caller_name">{{
                        incident.caller_name
                    }}</span>
                    <span
                        v-if="incident.caller_name && incident.caller_contact"
                    >
                        --
                    </span>
                    <span
                        v-if="incident.caller_contact"
                        class="font-mono text-[10px]"
                    >
                        {{ incident.caller_contact }}
                    </span>
                </div>
            </div>

            <div v-if="incident.coordinates" class="flex items-center gap-2">
                <svg
                    class="size-3 shrink-0 text-t-text-faint"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z"
                    />
                </svg>
                <span class="font-mono text-[10px] text-t-text-faint">
                    {{ incident.coordinates.lat.toFixed(5) }},
                    {{ incident.coordinates.lng.toFixed(5) }}
                </span>
            </div>

            <div class="flex items-center gap-2">
                <svg
                    class="size-3 shrink-0 text-t-text-faint"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <span class="text-[10px] text-t-text-faint">
                    {{ elapsedText }}
                </span>
            </div>
        </div>

        <!-- Notes -->
        <div v-if="incident.notes" class="border-b border-t-border px-3 py-2.5">
            <span
                class="mb-1 block font-mono text-[9px] font-bold tracking-[1.5px] text-t-text-faint uppercase"
            >
                NOTES
            </span>
            <p class="text-xs text-t-text-dim">
                {{ incident.notes }}
            </p>
        </div>

        <!-- Status Pipeline -->
        <div class="border-b border-t-border px-3 py-2.5">
            <StatusPipeline
                :incident-id="incident.id"
                :current-status="incident.status"
            />
        </div>

        <!-- Assignees -->
        <div class="border-b border-t-border px-3 py-2.5">
            <span
                class="mb-2 block font-mono text-[9px] font-bold tracking-[1.5px] text-t-text-faint uppercase"
            >
                ASSIGNEES
                <span
                    v-if="
                        incident.assigned_units &&
                        incident.assigned_units.length > 0
                    "
                    class="ml-1 text-t-accent"
                >
                    ({{ incident.assigned_units.length }})
                </span>
            </span>
            <div
                v-if="
                    incident.assigned_units &&
                    incident.assigned_units.length > 0
                "
                class="space-y-1.5"
            >
                <div
                    v-for="au in incident.assigned_units"
                    :key="au.unit_id"
                    class="flex items-center justify-between rounded border border-t-border bg-t-surface px-2.5 py-1.5"
                >
                    <button
                        class="font-mono text-[11px] font-bold text-t-text transition-colors hover:text-t-p1"
                        :title="'Click to unassign ' + au.callsign"
                        @click="handleUnassign(au.unit_id)"
                    >
                        {{ au.callsign }}
                    </button>
                    <AckTimerRing
                        :assigned-at="au.assigned_at"
                        :acknowledged-at="au.acknowledged_at"
                        @expired="emit('ack-expired')"
                    />
                </div>
            </div>
            <p v-else class="text-[10px] text-t-text-faint">
                No units assigned
            </p>
        </div>

        <!-- Dispatch: Available Units -->
        <div class="border-b border-t-border px-3 py-2.5">
            <span
                class="mb-2 block font-mono text-[9px] font-bold tracking-[1.5px] text-t-text-faint uppercase"
            >
                AVAILABLE UNITS
            </span>
            <div v-if="isLoadingNearby" class="space-y-2">
                <div class="h-10 animate-pulse rounded bg-t-border/30" />
                <div class="h-10 animate-pulse rounded bg-t-border/30" />
            </div>
            <div v-else-if="nearbyUnitsList.length > 0" class="space-y-1.5">
                <AssignmentChip
                    v-for="unit in nearbyUnitsList"
                    :key="unit.id"
                    :unit="unit"
                    :incident-id="incident.id"
                />
            </div>
            <p v-else class="text-[10px] text-t-text-faint">
                No available units nearby
            </p>
        </div>

        <!-- Timeline -->
        <div
            v-if="timelineEntries.length > 0"
            class="border-b border-t-border px-3 py-2.5"
        >
            <span
                class="mb-2 block font-mono text-[9px] font-bold tracking-[1.5px] text-t-text-faint uppercase"
            >
                TIMELINE
            </span>
            <div class="max-h-48 space-y-2 overflow-y-auto">
                <div
                    v-for="entry in timelineEntries"
                    :key="entry.id"
                    class="flex items-start gap-2"
                >
                    <div
                        class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full bg-t-surface-alt"
                    >
                        <svg
                            class="size-2.5 text-t-text-faint"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-[10px] text-t-text-dim">
                            {{ entry.event_type.replace(/_/g, ' ') }}
                        </div>
                        <div
                            v-if="entry.notes"
                            class="text-[9px] text-t-text-faint"
                        >
                            {{ entry.notes }}
                        </div>
                        <div class="flex items-center gap-1">
                            <span
                                v-if="entry.actor"
                                class="text-[9px] text-t-text-faint"
                            >
                                {{ entry.actor.name }}
                            </span>
                            <span
                                class="font-mono text-[8px] text-t-text-faint"
                            >
                                {{
                                    new Date(
                                        entry.created_at,
                                    ).toLocaleTimeString('en-US', {
                                        hour: '2-digit',
                                        minute: '2-digit',
                                        second: '2-digit',
                                        hour12: false,
                                    })
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mutual Aid button -->
        <div class="px-3 py-2.5">
            <button
                class="w-full rounded border border-t-border bg-t-surface px-3 py-1.5 font-mono text-[10px] font-bold tracking-wider text-t-text-dim transition-colors hover:border-t-accent/40 hover:text-t-accent"
                disabled
                title="Coming in Plan 04"
            >
                REQUEST MUTUAL AID
            </button>
        </div>
    </div>
</template>
