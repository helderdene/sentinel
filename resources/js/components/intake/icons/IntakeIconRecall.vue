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
        <path d="M2 6.5a4.5 4.5 0 1 1 1 3" />
        <polyline points="2,3.5 2,6.5 5,6.5" />
    </svg>
</template>
