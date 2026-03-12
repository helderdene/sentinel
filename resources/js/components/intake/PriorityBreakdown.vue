<script setup lang="ts">
import { computed } from 'vue';

import type { Incident, IncidentPriority } from '@/types/incident';

const props = defineProps<{
    triagedIncidents: Incident[];
}>();

type PriorityLevel = {
    key: IncidentPriority;
    label: string;
    color: string;
    count: number;
};

const levels = computed<PriorityLevel[]>(() => {
    const counts: Record<IncidentPriority, number> = {
        P1: 0,
        P2: 0,
        P3: 0,
        P4: 0,
    };

    for (const incident of props.triagedIncidents) {
        if (incident.priority in counts) {
            counts[incident.priority]++;
        }
    }

    return [
        {
            key: 'P1',
            label: 'Critical',
            color: 'var(--t-p1)',
            count: counts.P1,
        },
        {
            key: 'P2',
            label: 'Urgent',
            color: 'var(--t-p2)',
            count: counts.P2,
        },
        {
            key: 'P3',
            label: 'Standard',
            color: 'var(--t-p3)',
            count: counts.P3,
        },
        {
            key: 'P4',
            label: 'Low',
            color: 'var(--t-p4)',
            count: counts.P4,
        },
    ];
});

const total = computed(() => levels.value.reduce((sum, l) => sum + l.count, 0));

function barWidth(count: number): string {
    if (total.value === 0) {
        return '0%';
    }

    const pct = (count / total.value) * 100;

    if (count > 0 && pct < 4) {
        return '4%';
    }

    return `${pct}%`;
}
</script>

<template>
    <div class="mt-3">
        <p
            class="mb-2 font-mono text-[9px] font-medium tracking-[2px] text-t-text-faint uppercase"
        >
            Priority Breakdown
        </p>

        <div class="space-y-2">
            <div
                v-for="level in levels"
                :key="level.key"
                class="flex items-center gap-2"
            >
                <div class="flex w-16 shrink-0 items-center gap-1">
                    <span
                        class="font-mono text-[10px] font-bold"
                        :style="{ color: level.color }"
                    >
                        {{ level.key }}
                    </span>
                    <span class="text-[10px] text-t-text-dim">
                        {{ level.label }}
                    </span>
                </div>

                <div class="h-3 flex-1 overflow-hidden rounded-sm bg-t-surface">
                    <div
                        class="h-full rounded-sm transition-all duration-300"
                        :style="{
                            width: barWidth(level.count),
                            backgroundColor: level.color,
                        }"
                    />
                </div>

                <span
                    class="w-5 text-right font-mono text-[10px] font-bold text-t-text-dim"
                >
                    {{ level.count }}
                </span>
            </div>
        </div>
    </div>
</template>
