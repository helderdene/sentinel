import { computed, ref } from 'vue';

export function useIntakeSession() {
    const received = ref(0);
    const triaged = ref(0);
    const handleTimes = ref<number[]>([]);

    const pending = computed(() => received.value - triaged.value);

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
