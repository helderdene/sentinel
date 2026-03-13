<script setup lang="ts">
import { useIntervalFn } from '@vueuse/core';
import { computed, ref } from 'vue';
import type { IncidentPriority } from '@/types/dispatch';

const props = defineProps<{
    priority: IncidentPriority;
    createdAt: string;
}>();

const SLA_TARGETS_MINUTES: Record<IncidentPriority, number> = {
    P1: 5,
    P2: 10,
    P3: 20,
    P4: 30,
};

const targetMinutes = computed(() => SLA_TARGETS_MINUTES[props.priority] ?? 30);
const targetMs = computed(() => targetMinutes.value * 60 * 1000);

const elapsedMs = ref(0);

function updateElapsed(): void {
    elapsedMs.value = Date.now() - new Date(props.createdAt).getTime();
}

updateElapsed();

useIntervalFn(updateElapsed, 1000);

const percentage = computed(() => {
    const pct = (elapsedMs.value / targetMs.value) * 100;

    return Math.min(pct, 100);
});

const barColor = computed(() => {
    if (percentage.value > 80) {
        return 'var(--t-p1)';
    }

    if (percentage.value > 50) {
        return 'var(--t-p2)';
    }

    return 'var(--t-p4)';
});

const remainingText = computed(() => {
    const remainMs = targetMs.value - elapsedMs.value;

    if (remainMs <= 0) {
        const overMs = Math.abs(remainMs);
        const overMin = Math.floor(overMs / 60000);
        const overSec = Math.floor((overMs % 60000) / 1000);

        return `-${overMin}:${overSec.toString().padStart(2, '0')}`;
    }

    const min = Math.floor(remainMs / 60000);
    const sec = Math.floor((remainMs % 60000) / 1000);

    return `${min}:${sec.toString().padStart(2, '0')}`;
});
</script>

<template>
    <div class="space-y-1">
        <div class="flex items-center justify-between">
            <span
                class="font-mono text-[9px] font-bold tracking-[1.5px] text-t-text-faint uppercase"
            >
                SLA WINDOW
            </span>
            <span
                class="font-mono text-[10px] font-bold"
                :style="{ color: barColor }"
            >
                {{ remainingText }}
            </span>
        </div>
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-t-border/50">
            <div
                class="h-full rounded-full transition-all duration-300"
                :style="{
                    width: `${percentage}%`,
                    backgroundColor: barColor,
                }"
            />
        </div>
        <div class="flex items-center justify-between">
            <span class="font-mono text-[9px] text-t-text-faint">
                {{ Math.round(percentage) }}%
            </span>
            <span class="font-mono text-[9px] text-t-text-faint">
                {{ targetMinutes }}min target
            </span>
        </div>
    </div>
</template>
