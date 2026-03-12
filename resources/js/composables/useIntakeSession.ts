import { computed, ref } from 'vue';

export function useIntakeSession(initialReceived = 0, initialTriaged = 0) {
    const received = ref(initialReceived);
    const triaged = ref(initialTriaged);
    const handleTimes = ref<number[]>([]);

    const pending = computed(() => Math.max(0, received.value - triaged.value));

    const avgHandleTime = computed(() => {
        if (handleTimes.value.length === 0) {
            return 0;
        }

        const sum = handleTimes.value.reduce((a, b) => a + b, 0);

        return Math.round(sum / handleTimes.value.length / 1000);
    });

    function recordReceived(): void {
        received.value++;
    }

    function recordTriaged(handleTimeMs: number): void {
        triaged.value++;
        handleTimes.value.push(handleTimeMs);
    }

    return {
        received,
        triaged,
        pending,
        avgHandleTime,
        handleTimes,
        recordReceived,
        recordTriaged,
    };
}
