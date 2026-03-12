<script setup lang="ts">
withDefaults(
    defineProps<{
        size?: number;
        color?: string;
    }>(),
    {
        size: 14,
        color: 'currentColor',
    },
);
</script>

<template>
    <svg
        :width="size"
        :height="size"
        :viewBox="`0 0 14 14`"
        fill="none"
        :stroke="color"
        stroke-width="1.3"
        stroke-linecap="round"
        stroke-linejoin="round"
    >
        <path d="M7 13S2 8.5 2 5.5a5 5 0 0 1 10 0C12 8.5 7 13 7 13z" />
        <circle cx="7" cy="5.5" r="1.5" />
    </svg>
</template>
