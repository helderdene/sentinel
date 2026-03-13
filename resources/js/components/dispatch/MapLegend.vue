<script setup lang="ts">
const incidentLegend = [
    { label: 'P1 Critical', color: '#dc2626' },
    { label: 'P2 High', color: '#ea580c' },
    { label: 'P3 Medium', color: '#ca8a04' },
    { label: 'P4 Low', color: '#16a34a' },
];

const unitLegend = [
    { label: 'Available', color: '#16a34a' },
    { label: 'Dispatched / En Route', color: '#2563eb' },
    { label: 'On Scene', color: '#ca8a04' },
    { label: 'Offline', color: '#6b7280' },
];
</script>

<template>
    <div
        class="absolute bottom-4 left-4 z-10 w-40 rounded-lg border border-t-border bg-t-bg/90 p-3 backdrop-blur-sm dark:border-t-border dark:bg-[#0f172a]/90"
    >
        <!-- Incidents -->
        <div
            class="mb-2 font-mono text-[8px] font-bold tracking-[1.5px] text-t-text-faint uppercase"
        >
            INCIDENTS
        </div>
        <div class="mb-3 space-y-1">
            <div
                v-for="item in incidentLegend"
                :key="item.label"
                class="flex items-center gap-2"
            >
                <span
                    class="size-2.5 shrink-0 rounded-full"
                    :style="{ backgroundColor: item.color }"
                />
                <span class="text-[10px] text-t-text-dim">
                    {{ item.label }}
                </span>
            </div>
        </div>

        <!-- Units -->
        <div
            class="mb-2 font-mono text-[8px] font-bold tracking-[1.5px] text-t-text-faint uppercase"
        >
            UNITS
        </div>
        <div class="space-y-1">
            <div
                v-for="item in unitLegend"
                :key="item.label"
                class="flex items-center gap-2"
            >
                <span
                    class="size-2.5 shrink-0 rounded-full"
                    :style="{ backgroundColor: item.color }"
                />
                <span class="text-[10px] text-t-text-dim">
                    {{ item.label }}
                </span>
            </div>
        </div>
    </div>
</template>
