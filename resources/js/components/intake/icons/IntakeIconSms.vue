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
        <path
            d="M2.5 3h11a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H5l-3 2.5V4a1 1 0 0 1 1-1z"
        />
        <line x1="5" y1="6.5" x2="11" y2="6.5" />
        <line x1="5" y1="9" x2="9" y2="9" />
    </svg>
</template>
