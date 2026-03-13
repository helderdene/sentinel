import { useIntervalFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';

const ACK_TIMEOUT_SECONDS = 90;
const WARNING_THRESHOLD_SECONDS = 30;

export function useAckTimer(
    assignedAt: string,
    acknowledgedAt: string | null,
    onExpired?: () => void,
) {
    const remainingSeconds = ref(0);
    const hasExpiredFired = ref(false);

    function calculate(): number {
        const elapsed = Math.floor(
            (Date.now() - new Date(assignedAt).getTime()) / 1000,
        );

        return Math.max(0, ACK_TIMEOUT_SECONDS - elapsed);
    }

    remainingSeconds.value = calculate();

    const isAcknowledged = computed(() => acknowledgedAt !== null);
    const isExpired = computed(
        () => !isAcknowledged.value && remainingSeconds.value <= 0,
    );

    const colorClass = computed(() => {
        if (isAcknowledged.value) {
            return 'text-green-500';
        }

        if (isExpired.value) {
            return 'text-red-500';
        }

        if (remainingSeconds.value <= WARNING_THRESHOLD_SECONDS) {
            return 'text-red-500';
        }

        return 'text-green-500';
    });

    const displayTime = computed(() => {
        const s = remainingSeconds.value;
        const minutes = Math.floor(s / 60);
        const seconds = s % 60;

        return `${minutes}:${seconds.toString().padStart(2, '0')}`;
    });

    const { pause } = useIntervalFn(() => {
        if (isAcknowledged.value) {
            pause();

            return;
        }

        remainingSeconds.value = calculate();

        if (remainingSeconds.value <= 0 && !hasExpiredFired.value) {
            hasExpiredFired.value = true;
            onExpired?.();
        }
    }, 1000);

    watch(isAcknowledged, (acked) => {
        if (acked) {
            pause();
        }
    });

    return {
        remainingSeconds,
        isExpired,
        isAcknowledged,
        colorClass,
        displayTime,
    };
}
