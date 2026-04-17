<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import PriBadge from '@/components/intake/PriBadge.vue';
import {
    getCategoryComponent,
    getIncidentCategoryIcon,
} from '@/composables/useCategoryIcons';
import type { DispatchIncident } from '@/types/dispatch';

const props = defineProps<{
    incident: DispatchIncident;
    selected: boolean;
    unreadCount?: number;
}>();

const emit = defineEmits<{
    select: [id: string];
}>();

const priorityColors: Record<string, string> = {
    P1: 'var(--t-p1)',
    P2: 'var(--t-p2)',
    P3: 'var(--t-p3)',
    P4: 'var(--t-p4)',
};

const borderColor = computed(
    () => priorityColors[props.incident.priority] ?? priorityColors.P4,
);

const statusColorMap: Record<string, string> = {
    TRIAGED: 'var(--t-queued)',
    DISPATCHED: 'var(--t-unit-dispatched)',
    EN_ROUTE: 'var(--t-unit-enroute)',
    ON_SCENE: 'var(--t-unit-onscene)',
};

const statusColor = computed(
    () => statusColorMap[props.incident.status] ?? 'var(--t-text-faint)',
);

const statusLabel = computed(() => props.incident.status.replace(/_/g, ' '));

const priorityNumber = computed(() => {
    const num = parseInt(props.incident.priority.replace('P', ''), 10);

    return (num >= 1 && num <= 4 ? num : 4) as 1 | 2 | 3 | 4;
});

const elapsed = ref('');
let elapsedInterval: ReturnType<typeof setInterval> | null = null;

function updateElapsed(): void {
    const diff = Math.floor(
        (Date.now() - new Date(props.incident.created_at).getTime()) / 1000,
    );

    if (diff < 60) {
        elapsed.value = `${diff}s`;
    } else if (diff < 3600) {
        elapsed.value = `${Math.floor(diff / 60)}m`;
    } else {
        elapsed.value = `${Math.floor(diff / 3600)}h${Math.floor((diff % 3600) / 60)}m`;
    }
}

onMounted(() => {
    updateElapsed();
    elapsedInterval = setInterval(updateElapsed, 1000);
});

onUnmounted(() => {
    if (elapsedInterval) {
        clearInterval(elapsedInterval);
    }
});

const categoryIconComponent = computed(() =>
    getCategoryComponent(getIncidentCategoryIcon(props.incident.incident_type)),
);

const firstAssignedCallsign = computed(() => {
    if (
        props.incident.assigned_units &&
        props.incident.assigned_units.length > 0
    ) {
        return props.incident.assigned_units[0].callsign;
    }

    return null;
});
</script>

<template>
    <button
        class="flex w-full items-start gap-2 border-b border-t-border px-3 py-2.5 text-left transition-colors"
        :class="
            selected
                ? 'bg-t-brand/10 dark:bg-t-brand/15'
                : 'hover:bg-t-surface-alt/60'
        "
        :style="{
            borderLeftWidth: '4px',
            borderLeftColor: selected
                ? borderColor
                : `color-mix(in srgb, ${borderColor} 60%, transparent)`,
        }"
        @click="emit('select', incident.id)"
    >
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-1.5">
                <PriBadge :p="priorityNumber" size="sm" />
                <span class="font-mono text-[10px] text-t-text-faint">
                    {{ incident.incident_no }}
                </span>
                <span class="font-mono text-[10px] text-t-text-faint">
                    {{ elapsed }}
                </span>
            </div>
            <div class="mt-1 flex items-center gap-1.5 truncate">
                <component
                    :is="categoryIconComponent"
                    class="size-3 shrink-0 text-t-text-dim"
                />
                <span class="truncate text-xs font-semibold text-t-text">
                    {{ incident.incident_type?.name ?? 'Unclassified' }}
                </span>
            </div>
            <div class="mt-0.5 truncate text-[10px] text-t-text-dim">
                {{ incident.location_text }}
            </div>
        </div>
        <div class="flex shrink-0 flex-col items-end gap-1 pt-0.5">
            <span
                class="rounded px-1.5 py-[1px] font-mono text-[9px] font-bold"
                :style="{
                    backgroundColor: `color-mix(in srgb, ${statusColor} 12%, transparent)`,
                    color: statusColor,
                }"
            >
                {{ statusLabel }}
            </span>
            <span
                v-if="firstAssignedCallsign"
                class="font-mono text-[9px] text-t-text-faint"
            >
                {{ firstAssignedCallsign }}
            </span>
            <span
                v-if="props.unreadCount && props.unreadCount > 0"
                class="flex size-4 items-center justify-center rounded-full bg-t-accent font-mono text-[9px] font-bold text-white"
            >
                {{ props.unreadCount > 9 ? '9+' : props.unreadCount }}
            </span>
        </div>
    </button>
</template>
