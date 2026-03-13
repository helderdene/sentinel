<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { advanceStatus } from '@/actions/App/Http/Controllers/DispatchConsoleController';
import type { IncidentStatus } from '@/types/incident';

const props = defineProps<{
    incidentId: string;
    currentStatus: IncidentStatus;
}>();

interface PipelineStage {
    key: IncidentStatus;
    label: string;
}

const stages: PipelineStage[] = [
    { key: 'TRIAGED', label: 'REPORTED' },
    { key: 'DISPATCHED', label: 'DISPATCHED' },
    { key: 'EN_ROUTE', label: 'EN ROUTE' },
    { key: 'ON_SCENE', label: 'ON SCENE' },
    { key: 'RESOLVED', label: 'RESOLVED' },
];

const stageOrder: Record<string, number> = {
    TRIAGED: 0,
    DISPATCHED: 1,
    EN_ROUTE: 2,
    ON_SCENE: 3,
    RESOLVED: 4,
};

const currentIndex = computed(() => stageOrder[props.currentStatus] ?? 0);

function stageState(index: number): 'completed' | 'active' | 'future' {
    if (index < currentIndex.value) {
        return 'completed';
    }

    if (index === currentIndex.value) {
        return 'active';
    }

    return 'future';
}

const nextStatus = computed<IncidentStatus | null>(() => {
    const next = currentIndex.value + 1;

    if (next >= stages.length) {
        return null;
    }

    return stages[next].key;
});

const canAdvance = computed(
    () => nextStatus.value !== null && currentIndex.value >= 1,
);

const isAdvancing = ref(false);

function handleAdvance(): void {
    if (!nextStatus.value || isAdvancing.value) {
        return;
    }

    isAdvancing.value = true;

    router.post(
        advanceStatus.url(props.incidentId),
        { status: nextStatus.value },
        {
            preserveScroll: true,
            onFinish: () => {
                isAdvancing.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="space-y-3">
        <span
            class="font-mono text-[9px] font-bold tracking-[1.5px] text-t-text-faint uppercase"
        >
            STATUS
        </span>

        <!-- Pipeline -->
        <div class="flex items-center gap-0.5">
            <template v-for="(stage, index) in stages" :key="stage.key">
                <div
                    class="flex flex-col items-center"
                    :class="
                        index === 0 || index === stages.length - 1
                            ? 'flex-1'
                            : 'flex-1'
                    "
                >
                    <div
                        class="flex size-5 items-center justify-center rounded-full text-[8px] font-bold"
                        :class="{
                            'bg-t-accent text-white':
                                stageState(index) === 'active',
                            'bg-t-accent/30 text-t-accent':
                                stageState(index) === 'completed',
                            'bg-t-border/50 text-t-text-faint':
                                stageState(index) === 'future',
                        }"
                    >
                        <svg
                            v-if="stageState(index) === 'completed'"
                            class="size-3"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            stroke-width="3"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M5 13l4 4L19 7"
                            />
                        </svg>
                        <span v-else>{{ index + 1 }}</span>
                    </div>
                    <span
                        class="mt-1 text-center font-mono text-[7px] leading-tight font-bold"
                        :class="{
                            'text-t-accent': stageState(index) === 'active',
                            'text-t-accent/60':
                                stageState(index) === 'completed',
                            'text-t-text-faint': stageState(index) === 'future',
                        }"
                    >
                        {{ stage.label }}
                    </span>
                </div>
                <div
                    v-if="index < stages.length - 1"
                    class="mt-[-12px] h-0.5 flex-1"
                    :class="{
                        'bg-t-accent/30': index < currentIndex,
                        'bg-t-border/50': index >= currentIndex,
                    }"
                />
            </template>
        </div>

        <!-- Advance button -->
        <button
            v-if="canAdvance && nextStatus"
            class="w-full rounded bg-t-accent px-3 py-1.5 font-mono text-[10px] font-bold tracking-wider text-white transition-colors hover:bg-t-accent/90 disabled:opacity-50"
            :disabled="isAdvancing"
            @click="handleAdvance"
        >
            {{
                isAdvancing
                    ? 'ADVANCING...'
                    : `ADVANCE TO ${stages[currentIndex + 1]?.label}`
            }}
        </button>
    </div>
</template>
