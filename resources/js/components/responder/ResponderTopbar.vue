<script setup lang="ts">
import { computed } from 'vue';
import PriBadge from '@/components/intake/PriBadge.vue';
import type { ResponderIncident, ResponderUnit } from '@/types/responder';

const props = defineProps<{
    unit: ResponderUnit;
    incident: ResponderIncident | null;
    connectionStatus: string;
}>();

const priorityNumber = computed<1 | 2 | 3 | 4>(() => {
    if (!props.incident) {
        return 4;
    }

    const num = parseInt(props.incident.priority.replace('P', ''), 10);

    return (num >= 1 && num <= 4 ? num : 4) as 1 | 2 | 3 | 4;
});

const statusLabel = computed(() => {
    if (!props.incident) {
        return 'STANDBY';
    }

    return props.incident.status.replace('_', ' ');
});

const statusColor = computed(() => {
    if (!props.incident) {
        return 'var(--t-text-dim)';
    }

    const colors: Record<string, string> = {
        DISPATCHED: 'var(--t-unit-dispatched)',
        ACKNOWLEDGED: 'var(--t-unit-dispatched)',
        EN_ROUTE: 'var(--t-unit-enroute)',
        ON_SCENE: 'var(--t-unit-onscene)',
        RESOLVING: 'var(--t-p2)',
        RESOLVED: 'var(--t-online)',
    };

    return colors[props.incident.status] ?? 'var(--t-text-dim)';
});

const connectionDotColor = computed(() => {
    const colors: Record<string, string> = {
        online: 'var(--t-online)',
        reconnecting: 'var(--t-p3)',
        disconnected: 'var(--t-p1)',
    };

    return colors[props.connectionStatus] ?? 'var(--t-text-dim)';
});
</script>

<template>
    <header
        class="flex h-[44px] shrink-0 items-center justify-between border-b border-t-border bg-t-surface px-3"
    >
        <!-- Left: callsign + connection dot -->
        <div class="flex items-center gap-1.5">
            <span
                class="size-2 shrink-0 rounded-full"
                :style="{ backgroundColor: connectionDotColor }"
            />
            <span class="font-mono text-sm font-bold text-t-text">
                {{ unit.callsign }}
            </span>
        </div>

        <!-- Center: incident number + priority badge -->
        <div class="flex items-center gap-2">
            <template v-if="incident">
                <span class="font-mono text-xs text-t-text-mid">
                    {{ incident.incident_no }}
                </span>
                <PriBadge :p="priorityNumber" size="sm" />
            </template>
            <span v-else class="text-xs text-t-text-faint"> Standing By </span>
        </div>

        <!-- Right: status chip -->
        <span
            class="rounded-full px-2 py-0.5 font-mono text-[10px] font-bold text-white"
            :style="{
                backgroundColor: statusColor,
            }"
        >
            {{ statusLabel }}
        </span>
    </header>
</template>
