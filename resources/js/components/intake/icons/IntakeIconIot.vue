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
        <line x1="8" y1="6" x2="8" y2="14" />
        <line x1="5" y1="14" x2="11" y2="14" />
        <circle cx="8" cy="5" r="1" :fill="color" stroke="none" />
        <path d="M5.5 3.5a3.5 3.5 0 0 1 5 0" />
        <path d="M3.5 1.5a6.5 6.5 0 0 1 9 0" />
    </svg>
</template>
