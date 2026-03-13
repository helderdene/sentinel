<script setup lang="ts">
import { useIntervalFn } from '@vueuse/core';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { acknowledge as acknowledgeAction } from '@/actions/App/Http/Controllers/ResponderController';
import PriBadge from '@/components/intake/PriBadge.vue';
import { useAlertSystem } from '@/composables/useAlertSystem';
import type { AssignmentPayload } from '@/types/responder';

const ACK_TIMEOUT_SECONDS = 90;

const props = defineProps<{
    incident: AssignmentPayload;
    userId: number;
}>();

const emit = defineEmits<{
    acknowledged: [];
}>();

const alertSystem = useAlertSystem();
const isSubmitting = ref(false);
const isExpired = ref(false);
const remainingSeconds = ref(ACK_TIMEOUT_SECONDS);
const startedAt = ref(Date.now());

const { pause: pauseTimer } = useIntervalFn(
    () => {
        const elapsed = Math.floor((Date.now() - startedAt.value) / 1000);

        remainingSeconds.value = Math.max(0, ACK_TIMEOUT_SECONDS - elapsed);

        if (remainingSeconds.value <= 0 && !isExpired.value) {
            isExpired.value = true;
        }
    },
    1000,
    { immediate: false },
);

const formattedTime = computed(() => {
    const s = remainingSeconds.value;
    const minutes = Math.floor(s / 60);
    const seconds = s % 60;

    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
});

let audioLoopInterval: ReturnType<typeof setInterval> | null = null;

const priorityNumber = computed(() => {
    const map: Record<string, 1 | 2 | 3 | 4> = {
        P1: 1,
        P2: 2,
        P3: 3,
        P4: 4,
    };

    return map[props.incident.priority] ?? 4;
});

const borderColorClass = computed(() => {
    const map: Record<string, string> = {
        P1: 'border-t-p1',
        P2: 'border-t-p2',
        P3: 'border-t-p3',
        P4: 'border-t-p4',
    };

    return map[props.incident.priority] ?? 'border-t-p4';
});

const isPulsingBorder = computed(() => props.incident.priority === 'P1');

const expandedNotes = ref(false);

const truncatedNotes = computed(() => {
    if (!props.incident.notes) {
        return '';
    }

    if (props.incident.notes.length <= 120 || expandedNotes.value) {
        return props.incident.notes;
    }

    return props.incident.notes.slice(0, 120) + '...';
});

const hasLongNotes = computed(
    () => props.incident.notes !== null && props.incident.notes.length > 120,
);

function getXsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

async function handleAcknowledge(): Promise<void> {
    if (isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;

    try {
        const route = acknowledgeAction({
            incident: String(props.incident.id),
        });

        await fetch(route.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: JSON.stringify({
                unit_id: props.incident.unit_id,
            }),
        });

        pauseTimer();
        stopAudioLoop();
        emit('acknowledged');
    } catch {
        isSubmitting.value = false;
    }
}

function stopAudioLoop(): void {
    if (audioLoopInterval !== null) {
        clearInterval(audioLoopInterval);
        audioLoopInterval = null;
    }
}

onMounted(() => {
    startedAt.value = Date.now();
    alertSystem.playPriorityTone(props.incident.priority);

    audioLoopInterval = setInterval(() => {
        alertSystem.playPriorityTone(props.incident.priority);
    }, 15_000);
});

onUnmounted(() => {
    pauseTimer();
    stopAudioLoop();
});
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex flex-col bg-t-bg"
        :class="[
            `border-4 ${borderColorClass}`,
            isPulsingBorder ? 'animate-pulse-border' : '',
        ]"
    >
        <div class="flex flex-1 flex-col items-center justify-center px-6">
            <PriBadge :p="priorityNumber" size="lg" class="mb-4" />

            <h1
                class="mb-2 text-center font-sans text-[26px] font-extrabold text-t-text"
            >
                {{ incident.incident_type ?? 'Unknown Incident' }}
            </h1>

            <p
                v-if="incident.location_text"
                class="mb-1 text-center text-[14px] text-t-text-mid"
            >
                {{ incident.location_text }}
            </p>

            <p
                v-if="incident.barangay"
                class="mb-3 text-center text-[13px] text-t-text-dim"
            >
                Brgy. {{ incident.barangay }}
            </p>

            <div
                v-if="incident.notes"
                class="mb-4 w-full max-w-sm rounded-[10px] border border-t-border bg-t-surface p-3 shadow-[0_1px_3px_rgba(0,0,0,.04)]"
            >
                <p class="text-sm text-t-text-mid">
                    {{ truncatedNotes }}
                </p>
                <button
                    v-if="hasLongNotes"
                    type="button"
                    class="mt-1 text-xs font-medium text-t-accent"
                    @click="expandedNotes = !expandedNotes"
                >
                    {{ expandedNotes ? 'Show less' : 'Show more' }}
                </button>
            </div>

            <p class="font-mono text-[11px] tracking-[1.5px] text-t-text-dim">
                {{ incident.incident_no }}
            </p>
        </div>

        <div class="shrink-0 px-4 pb-6">
            <button
                type="button"
                class="flex min-h-[56px] w-full items-center justify-center rounded-[13px] font-mono text-[16px] font-bold tracking-wide text-white transition-transform active:scale-[0.98]"
                :class="isExpired ? 'bg-amber-600' : 'bg-t-accent'"
                :style="{
                    boxShadow: isExpired
                        ? '0 8px 24px rgba(217, 119, 6, 0.31)'
                        : '0 8px 24px rgba(37, 99, 235, 0.31)',
                }"
                :disabled="isSubmitting"
                @click="handleAcknowledge"
            >
                <span v-if="isSubmitting">ACKNOWLEDGING...</span>
                <span v-else-if="isExpired">LATE ACKNOWLEDGE</span>
                <span v-else> ACKNOWLEDGE ({{ formattedTime }}) </span>
            </button>

            <p v-if="isExpired" class="mt-2 text-center text-xs text-amber-500">
                Timer expired
            </p>
        </div>
    </div>
</template>

<style scoped>
@keyframes pulse-border {
    0%,
    100% {
        border-color: var(--t-p1);
        opacity: 1;
    }
    50% {
        border-color: var(--t-p1);
        opacity: 0.4;
    }
}

.animate-pulse-border {
    animation: pulse-border 1.5s ease-in-out infinite;
}
</style>
