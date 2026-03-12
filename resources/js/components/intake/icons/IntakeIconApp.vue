<script setup lang="ts">
withDefaults(
    defineProps<{
        size?: number;
        color?: string;
    }>(),
    {
        size: 16,
        color: 'currentColor',
    },
);
</script>

<template>
    <svg
        :width="size"
        :height="size"
        :viewBox="`0 0 16 16`"
        fill="none"
        :stroke="color"
        stroke-width="1.3"
        stroke-linecap="round"
        stroke-linejoin="round"
    >
        <rect x="4" y="1.5" width="8" height="13" rx="1.5" />
        <line x1="6.5" y1="11.5" x2="9.5" y2="11.5" />
        <circle cx="8" cy="5.5" r="0.5" :fill="color" stroke="none" />
    </svg>
</template>
