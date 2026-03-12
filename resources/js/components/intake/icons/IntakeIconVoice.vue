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
            d="M3.5 2.5h3l1 3-1.5 1a7.5 7.5 0 0 0 3.5 3.5l1-1.5 3 1v3c0 .5-.5 1-1 1C5.5 14 2 10.5 2.5 3.5c0-.5.5-1 1-1z"
        />
    </svg>
</template>
