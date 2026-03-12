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
        <circle cx="9" cy="2.5" r="1.5" />
        <path d="M7 6l-2 5h2l1 4" />
        <path d="M9 6l2 3.5" />
        <path d="M5 8.5l2-2.5h4" />
    </svg>
</template>
