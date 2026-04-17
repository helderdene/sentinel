<script setup lang="ts">
import { useAckTimer } from '@/composables/useAckTimer';

const props = defineProps<{
    assignedAt: string;
    acknowledgedAt: string | null;
}>();

const emit = defineEmits<{
    expired: [];
}>();

const { isExpired, isAcknowledged, colorClass, displayTime } = useAckTimer(
    props.assignedAt,
    () => props.acknowledgedAt,
    () => emit('expired'),
);
</script>

<template>
    <div class="inline-flex items-center gap-1" :class="colorClass">
        <template v-if="isAcknowledged">
            <svg
                class="size-3.5"
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
            <span class="font-mono text-[10px] font-bold">ACK</span>
        </template>
        <template v-else-if="isExpired">
            <svg
                class="size-3.5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                stroke-width="2.5"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
                />
            </svg>
            <span class="font-mono text-[10px] font-bold">EXPIRED</span>
        </template>
        <template v-else>
            <span class="font-mono text-[11px] font-bold tabular-nums">
                {{ displayTime }}
            </span>
        </template>
    </div>
</template>
