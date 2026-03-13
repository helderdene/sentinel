<script setup lang="ts">
import { ChevronRight } from 'lucide-vue-next';
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
        color: '#2563eb',
    },
    ACKNOWLEDGED: {
        label: 'EN ROUTE',
        nextStatus: 'EN_ROUTE',
        color: '#4f46e5',
    },
    EN_ROUTE: {
        label: 'ARRIVED ON SCENE',
        nextStatus: 'ON_SCENE',
        color: '#ca8a04',
    },
    ON_SCENE: {
        label: 'RESOLVING',
        nextStatus: 'RESOLVING',
        color: '#ea580c',
    },
};

const currentAction = computed(() => {
    if (!props.currentStatus) {
        return null;
    }

    return statusActions[props.currentStatus] ?? null;
});

const isVisible = computed(() => currentAction.value !== null);

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
    <div v-if="isVisible" class="shrink-0 px-3 pt-2 pb-1">
        <button
            type="button"
            class="flex min-h-[52px] w-full items-center justify-center gap-2 rounded-xl font-mono text-sm font-bold tracking-wide text-white shadow-lg transition-transform active:scale-[0.98]"
            :style="{
                backgroundColor: currentAction!.color,
            }"
            @click="handleClick"
        >
            <span v-if="showTimer" class="mr-1 opacity-80">
                {{ formattedTimer }}
            </span>
            <span>{{ currentAction!.label }}</span>
            <ChevronRight :size="18" />
        </button>
    </div>
</template>
