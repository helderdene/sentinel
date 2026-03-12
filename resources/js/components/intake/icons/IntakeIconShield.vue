<script setup lang="ts">
withDefaults(
    defineProps<{
        size?: number;
        color?: string;
    }>(),
    {
        size: 12,
        color: 'currentColor',
    },
);
</script>

<template>
    <svg
        :width="size"
        :height="size"
        :viewBox="`0 0 12 12`"
        fill="none"
        :stroke="color"
        stroke-width="1.3"
        stroke-linecap="round"
        stroke-linejoin="round"
    >
        <path d="M6 1L1.5 3v3.5c0 3 2 4.5 4.5 5.5 2.5-1 4.5-2.5 4.5-5.5V3L6 1z" />
        <polyline points="4,6 5.5,7.5 8,4.5" />
    </svg>
</template>
