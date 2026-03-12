<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import IntakeIconOverride from '@/components/intake/icons/IntakeIconOverride.vue';
import IntakeIconPin from '@/components/intake/icons/IntakeIconPin.vue';
import IntakeIconRecall from '@/components/intake/icons/IntakeIconRecall.vue';
import PriBadge from '@/components/intake/PriBadge.vue';
import type { Incident, IncidentPriority } from '@/types/incident';

const props = defineProps<{
    incident: Incident;
    canOverride: boolean;
    canRecall: boolean;
}>();

const emit = defineEmits<{
    overridden: [incidentId: string, newPriority: IncidentPriority];
    recalled: [incidentId: string];
}>();

const showPriorityPicker = ref(false);

const priorityColors: Record<number, string> = {
    1: 'var(--t-p1)',
    2: 'var(--t-p2)',
    3: 'var(--t-p3)',
    4: 'var(--t-p4)',
};

const borderColor = computed(
    () =>
        priorityColors[parseInt(props.incident.priority.replace('P', ''))] ??
        priorityColors[4],
);

function timeElapsed(createdAt: string): string {
    const diff = Math.floor(
        (Date.now() - new Date(createdAt).getTime()) / 1000,
    );

    if (diff < 60) {
        return `${diff}s`;
    }

    if (diff < 3600) {
        return `${Math.floor(diff / 60)}m`;
    }

    return `${Math.floor(diff / 3600)}h ${Math.floor((diff % 3600) / 60)}m`;
}

function handleOverride(priority: IncidentPriority): void {
    showPriorityPicker.value = false;

    router.post(
        `/intake/${props.incident.id}/override-priority`,
        { priority },
        {
            preserveScroll: true,
            onSuccess: () => {
                emit('overridden', props.incident.id, priority);
            },
        },
    );
}

function handleRecall(): void {
    router.post(
        `/intake/${props.incident.id}/recall`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                emit('recalled', props.incident.id);
            },
        },
    );
}

const priorityLevels: IncidentPriority[] = ['P1', 'P2', 'P3', 'P4'];
const currentPriorityNum = computed(() =>
    parseInt(props.incident.priority.replace('P', '')),
);
</script>

<template>
    <div
        class="rounded-[7px] bg-t-surface shadow-[0_1px_3px_rgba(0,0,0,0.04)]"
        :style="{
            borderLeft: `3px solid ${borderColor}`,
            padding: '10px 12px',
        }"
    >
        <!-- Top row: PriBadge + incident_no + time -->
        <div class="flex items-center gap-2">
            <PriBadge :p="currentPriorityNum as 1 | 2 | 3 | 4" size="sm" />
            <span class="font-mono text-[10px] text-t-text-faint">
                {{ incident.incident_no }}
            </span>
            <span class="ml-auto font-mono text-[10px] text-t-text-faint">
                {{ timeElapsed(incident.created_at) }}
            </span>
        </div>

        <!-- Middle: type + location -->
        <div class="mt-1.5">
            <p class="text-[13px] font-medium text-t-text">
                {{ incident.incident_type?.name ?? 'Unknown Type' }}
            </p>
            <p
                v-if="incident.location_text"
                class="mt-0.5 flex items-center gap-1 text-[11px] text-t-text-dim"
            >
                <IntakeIconPin :size="10" color="var(--t-text-dim)" />
                {{ incident.location_text }}
            </p>
        </div>

        <!-- Bottom row: supervisor actions -->
        <div
            v-if="canOverride || canRecall"
            class="mt-2 flex items-center gap-2"
        >
            <button
                v-if="canOverride"
                class="flex items-center gap-1 rounded-md px-2 py-1 text-[10px] font-medium transition-colors"
                :style="{
                    backgroundColor: 'rgba(124,58,237,0.07)',
                    border: '1px solid rgba(124,58,237,0.25)',
                    color: '#7c3aed',
                }"
                @click="showPriorityPicker = !showPriorityPicker"
            >
                <IntakeIconOverride :size="11" color="#7c3aed" />
                Override
            </button>

            <button
                v-if="canRecall"
                class="flex items-center gap-1 rounded-md px-2 py-1 text-[10px] font-medium transition-colors"
                :style="{
                    backgroundColor: 'rgba(220,38,38,0.06)',
                    border: '1px solid rgba(220,38,38,0.2)',
                    color: '#dc2626',
                }"
                @click="handleRecall"
            >
                <IntakeIconRecall :size="11" color="#dc2626" />
                Recall
            </button>
        </div>

        <!-- Inline priority picker -->
        <div
            v-if="showPriorityPicker"
            class="mt-2 grid grid-cols-4 gap-1"
        >
            <button
                v-for="p in priorityLevels"
                :key="p"
                class="rounded px-2 py-1 font-mono text-[10px] font-bold transition-colors"
                :disabled="p === incident.priority"
                :style="{
                    backgroundColor:
                        p === incident.priority
                            ? 'transparent'
                            : `color-mix(in srgb, ${priorityColors[parseInt(p.replace('P', ''))]} 10%, transparent)`,
                    color: priorityColors[parseInt(p.replace('P', ''))],
                    opacity: p === incident.priority ? 0.4 : 1,
                    cursor:
                        p === incident.priority
                            ? 'not-allowed'
                            : 'pointer',
                }"
                @click="handleOverride(p)"
            >
                {{ p }}
            </button>
        </div>
    </div>
</template>
