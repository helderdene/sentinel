<script setup lang="ts">
import { computed } from 'vue';
import PriBadge from '@/components/intake/PriBadge.vue';
import type { DispatchIncident, DispatchUnit } from '@/types/dispatch';

const props = defineProps<{
    unit: DispatchUnit;
    incidents: DispatchIncident[];
}>();

const emit = defineEmits<{
    back: [];
}>();

const statusDotColors: Record<string, string> = {
    AVAILABLE: 'var(--t-unit-available)',
    DISPATCHED: 'var(--t-unit-dispatched)',
    EN_ROUTE: 'var(--t-unit-enroute)',
    ON_SCENE: 'var(--t-unit-onscene)',
    OFFLINE: 'var(--t-unit-offline)',
};

const dotColor = computed(
    () => statusDotColors[props.unit.status] ?? 'var(--t-text-faint)',
);

const statusText = computed(() => props.unit.status.replace(/_/g, ' '));

const activeIncident = computed(() => {
    if (!props.unit.active_incident_id) {
        return null;
    }

    return (
        props.incidents.find((i) => i.id === props.unit.active_incident_id) ??
        null
    );
});

const activeIncidentPriority = computed(() => {
    if (!activeIncident.value) {
        return 4 as const;
    }

    const num = parseInt(activeIncident.value.priority.replace('P', ''), 10);

    return (num >= 1 && num <= 4 ? num : 4) as 1 | 2 | 3 | 4;
});
</script>

<template>
    <div class="flex h-full flex-col">
        <!-- Header -->
        <div
            class="flex items-start justify-between border-b border-t-border px-3 py-2.5"
        >
            <div>
                <div class="font-mono text-lg font-bold text-t-text">
                    {{ unit.callsign }}
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-t-text-dim">
                        {{ unit.type }}
                    </span>
                    <span class="text-[10px] text-t-text-faint">
                        {{ unit.agency }}
                    </span>
                </div>
            </div>
            <button
                class="flex size-6 shrink-0 items-center justify-center rounded text-t-text-faint transition-colors hover:bg-t-surface-alt hover:text-t-text"
                @click="emit('back')"
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
                        d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"
                    />
                </svg>
            </button>
        </div>

        <!-- Status -->
        <div class="space-y-3 border-b border-t-border px-3 py-2.5">
            <div class="flex items-center gap-2">
                <span
                    class="size-2.5 shrink-0 rounded-full"
                    :style="{ backgroundColor: dotColor }"
                />
                <span
                    class="font-mono text-xs font-bold"
                    :style="{ color: dotColor }"
                >
                    {{ statusText }}
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
                        d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"
                    />
                </svg>
                <span class="text-xs text-t-text-dim">
                    Crew: {{ unit.crew_capacity }}
                </span>
            </div>
        </div>

        <!-- Current assignment -->
        <div v-if="activeIncident" class="border-b border-t-border px-3 py-2.5">
            <span
                class="mb-2 block font-mono text-[9px] font-bold tracking-[1.5px] text-t-text-faint uppercase"
            >
                CURRENT ASSIGNMENT
            </span>
            <div
                class="rounded border border-t-border bg-t-surface px-2.5 py-2"
            >
                <div class="flex items-center gap-2">
                    <span class="font-mono text-[11px] font-bold text-t-text">
                        {{ activeIncident.incident_no }}
                    </span>
                    <PriBadge :p="activeIncidentPriority" size="sm" />
                </div>
                <div class="mt-1 text-[10px] text-t-text-dim">
                    {{ activeIncident.incident_type?.name ?? 'Unclassified' }}
                </div>
            </div>
        </div>

        <!-- Coordinates -->
        <div
            v-if="unit.coordinates"
            class="border-b border-t-border px-3 py-2.5"
        >
            <span
                class="mb-1 block font-mono text-[9px] font-bold tracking-[1.5px] text-t-text-faint uppercase"
            >
                COORDINATES
            </span>
            <span class="font-mono text-[10px] text-t-text-dim">
                {{ unit.coordinates.lat.toFixed(5) }},
                {{ unit.coordinates.lng.toFixed(5) }}
            </span>
        </div>
    </div>
</template>
