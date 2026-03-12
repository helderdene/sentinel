<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    received: number;
    triaged: number;
    pending: number;
    avgHandleTime: number;
}>();

const avgLabel = computed(() => {
    const s = props.avgHandleTime;

    if (s === 0) {
        return '0s';
    }

    if (s < 60) {
        return `${s}s`;
    }

    return `${Math.floor(s / 60)}m ${s % 60}s`;
});
</script>

<template>
    <div class="rounded-lg border border-t-border bg-t-surface-alt p-3">
        <div class="grid grid-cols-2 gap-3">
            <div class="flex flex-col">
                <span class="font-mono text-[20px] font-bold text-t-text">
                    {{ received }}
                </span>
                <span class="text-[11px] text-t-text-dim">Received</span>
            </div>

            <div class="flex flex-col">
                <span
                    class="font-mono text-[20px] font-bold text-t-online"
                >
                    {{ triaged }}
                </span>
                <span class="text-[11px] text-t-text-dim">Triaged</span>
            </div>

            <div class="flex flex-col">
                <span
                    class="font-mono text-[20px] font-bold text-t-accent"
                >
                    {{ pending }}
                </span>
                <span class="text-[11px] text-t-text-dim">Pending</span>
            </div>

            <div class="flex flex-col">
                <span class="font-mono text-[20px] font-bold text-t-text">
                    {{ avgLabel }}
                </span>
                <span class="text-[11px] text-t-text-dim">
                    Avg Handle
                </span>
            </div>
        </div>
    </div>
</template>
