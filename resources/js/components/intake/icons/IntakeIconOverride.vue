<script setup lang="ts">
withDefaults(
    defineProps<{
        size?: number;
        color?: string;
    }>(),
    {
        size: 13,
        color: 'currentColor',
    },
);
</script>

<template>
    <svg
        :width="size"
        :height="size"
        :viewBox="`0 0 13 13`"
        fill="none"
        :stroke="color"
        stroke-width="1.3"
        stroke-linecap="round"
        stroke-linejoin="round"
    >
        <line x1="6.5" y1="2" x2="6.5" y2="8" />
        <polyline points="4,4.5 6.5,2 9,4.5" />
        <line x1="3" y1="10" x2="10" y2="10" />
        <line x1="3" y1="12" x2="10" y2="12" />
    </svg>
</template>
