<script setup lang="ts">
import { computed } from 'vue';
import type { IncidentStatus } from '@/types/responder';

const props = defineProps<{
    currentStatus: IncidentStatus | null;
    ackTimerRemaining: number;
}>();

const emit = defineEmits<{
    advance: [];
    'show-outcome-sheet': [];
}>();

interface StatusAction {
    label: string;
    nextStatus: IncidentStatus;
    color: string;
}

const statusActions: Record<string, StatusAction> = {
    DISPATCHED: {
        label: 'ACKNOWLEDGE',
        nextStatus: 'ACKNOWLEDGED',
        color: '#378ADD',
    },
    ACKNOWLEDGED: {
        label: 'EN ROUTE',
        nextStatus: 'EN_ROUTE',
        color: '#378ADD',
    },
    EN_ROUTE: {
        label: 'ARRIVED ON SCENE',
        nextStatus: 'ON_SCENE',
        color: '#EF9F27',
    },
    ON_SCENE: {
        label: 'RESOLVING',
        nextStatus: 'RESOLVING',
        color: '#E24B4A',
    },
};

const currentAction = computed(() => {
    if (!props.currentStatus) {
        return null;
    }

    return statusActions[props.currentStatus] ?? null;
});

const isVisible = computed(
    () => currentAction.value !== null && props.currentStatus !== 'DISPATCHED',
);

const showTimer = computed(
    () => props.currentStatus === 'DISPATCHED' && props.ackTimerRemaining > 0,
);

const formattedTimer = computed(() => {
    const s = props.ackTimerRemaining;
    const minutes = Math.floor(s / 60);
    const seconds = s % 60;

    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
});

function handleClick(): void {
    if (!currentAction.value) {
        return;
    }

    if (currentAction.value.nextStatus === 'RESOLVING') {
        emit('show-outcome-sheet');
    } else {
        emit('advance');
    }
}
</script>

<template>
    <div
        v-if="isVisible"
        class="pointer-events-none fixed inset-x-0 bottom-[80px] shrink-0 px-3 pt-8 pb-3"
        :style="{
            background: 'linear-gradient(transparent, var(--t-bg) 30%)',
        }"
    >
        <button
            type="button"
            class="pointer-events-auto flex min-h-[52px] w-full items-center justify-center gap-2 rounded-[13px] text-[16px] font-bold tracking-wide text-white transition-transform active:scale-[0.98]"
            :style="{
                backgroundColor: currentAction!.color,
                boxShadow: `0 6px 20px ${currentAction!.color}45`,
            }"
            @click="handleClick"
        >
            <span
                v-if="showTimer"
                class="mr-1 font-mono text-[14px] opacity-80"
            >
                {{ formattedTimer }}
            </span>
            <span>{{ currentAction!.label }}</span>
            <svg
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                style="color: currentColor"
            >
                <path
                    d="M9 18L15 12L9 6"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
        </button>
    </div>
</template>
