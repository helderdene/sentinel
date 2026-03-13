<script setup lang="ts">
import { STATUS_COLORS, STATUS_SEQUENCE } from '@/types';
import { computed } from 'vue';

const props = defineProps<{
    currentStatus: string;
}>();

const currentIndex = computed(() => {
    const idx = STATUS_SEQUENCE.indexOf(
        props.currentStatus as (typeof STATUS_SEQUENCE)[number]
    );

    return idx >= 0 ? idx : 0;
});

function stageColor(stage: string): string {
    return STATUS_COLORS[stage] ?? '#64748b';
}

function isCompleted(idx: number): boolean {
    return idx < currentIndex.value;
}

function isCurrent(idx: number): boolean {
    return idx === currentIndex.value;
}
</script>

<template>
    <div class="flex items-center gap-0">
        <template v-for="(stage, idx) in STATUS_SEQUENCE" :key="stage">
            <!-- Stage dot + label -->
            <div class="flex flex-col items-center gap-1.5">
                <div
                    class="relative flex h-8 w-8 items-center justify-center rounded-full border-2 transition-all"
                    :style="{
                        borderColor:
                            isCompleted(idx) || isCurrent(idx)
                                ? stageColor(stage)
                                : 'var(--t-border-med)',
                        backgroundColor:
                            isCompleted(idx) || isCurrent(idx)
                                ? stageColor(stage)
                                : 'transparent',
                    }"
                >
                    <!-- Check icon for completed -->
                    <svg
                        v-if="isCompleted(idx)"
                        width="14"
                        height="14"
                        viewBox="0 0 14 14"
                        fill="none"
                    >
                        <path
                            d="M3 7L6 10L11 4"
                            stroke="white"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                    <!-- Pulse dot for current -->
                    <div
                        v-else-if="isCurrent(idx)"
                        class="h-2.5 w-2.5 animate-pulse rounded-full bg-white"
                    />
                    <!-- Empty dot for future -->
                    <div
                        v-else
                        class="h-2 w-2 rounded-full"
                        style="background-color: var(--t-border-med)"
                    />
                </div>
                <span
                    class="text-center font-mono text-[9px] uppercase tracking-wider"
                    :style="{
                        color:
                            isCompleted(idx) || isCurrent(idx)
                                ? stageColor(stage)
                                : 'var(--t-text-faint)',
                        fontWeight: isCurrent(idx) ? '700' : '400',
                    }"
                >
                    {{ stage }}
                </span>
            </div>

            <!-- Connector line (not after last) -->
            <div
                v-if="idx < STATUS_SEQUENCE.length - 1"
                class="mb-5 h-0.5 flex-1"
                :style="{
                    backgroundColor: isCompleted(idx)
                        ? stageColor(stage)
                        : 'var(--t-border)',
                    minWidth: '20px',
                }"
            />
        </template>
    </div>
</template>
