<script setup lang="ts">
const STOPS = [
    { value: '0', color: '#E8F4FD' },
    { value: '5', color: '#A8D4F0' },
    { value: '15', color: '#6AB4E3' },
    { value: '30', color: '#378ADD' },
    { value: '50+', color: '#185FA5' },
];
</script>

<template>
    <div
        class="absolute bottom-4 left-4 z-10 rounded-[var(--radius)] border border-border bg-card/90 px-3 py-2 shadow-[var(--shadow-1)] backdrop-blur"
    >
        <p
            class="mb-1.5 font-mono text-[10px] font-bold tracking-[2.5px] text-t-text-faint uppercase"
        >
            Incidents
        </p>
        <div class="flex items-center gap-0">
            <div
                v-for="stop in STOPS"
                :key="stop.value"
                class="flex flex-col items-center"
            >
                <div
                    class="h-3 w-10"
                    :style="{ backgroundColor: stop.color }"
                />
                <span class="mt-0.5 font-mono text-[9px] text-t-text-faint">
                    {{ stop.value }}
                </span>
            </div>
        </div>
    </div>
</template>
