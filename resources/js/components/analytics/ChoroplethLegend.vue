<script setup lang="ts">
const STOPS = [
    { value: '0', color: '#eff6ff' },
    { value: '5', color: '#bfdbfe' },
    { value: '15', color: '#60a5fa' },
    { value: '30', color: '#2563eb' },
    { value: '50+', color: '#1d4ed8' },
];
</script>

<template>
    <div
        class="absolute bottom-4 left-4 z-10 rounded-lg border border-neutral-200 bg-white/90 px-3 py-2 shadow-sm backdrop-blur dark:border-neutral-700 dark:bg-zinc-900/90"
    >
        <p
            class="mb-1.5 text-[10px] font-medium tracking-wide text-neutral-500 uppercase dark:text-neutral-400"
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
                <span
                    class="mt-0.5 text-[9px] text-neutral-500 dark:text-neutral-400"
                >
                    {{ stop.value }}
                </span>
            </div>
        </div>
    </div>
</template>
