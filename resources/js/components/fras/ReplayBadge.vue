<script setup lang="ts">
defineProps<{ count: number }>();
</script>

<template>
    <span
        v-if="count >= 2"
        class="inline-flex items-center rounded-full border bg-[color-mix(in_srgb,var(--t-accent)_15%,transparent)] px-2 py-0.5 font-mono text-[10px] tracking-[1.5px] text-t-accent uppercase"
        :style="{
            borderColor:
                'color-mix(in srgb, var(--t-accent) 40%, transparent)',
        }"
        :aria-label="`This personnel has appeared on this camera ${count} times in the last 24 hours`"
    >
        ×{{ count }} today
    </span>
</template>
