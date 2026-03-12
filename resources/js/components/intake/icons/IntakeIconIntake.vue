<script setup lang="ts">
withDefaults(
    defineProps<{
        size?: number;
        color?: string;
    }>(),
    {
        size: 18,
        color: 'currentColor',
    },
);
</script>

<template>
    <svg
        :width="size"
        :height="size"
        :viewBox="`0 0 18 18`"
        fill="none"
        :stroke="color"
        stroke-width="1.3"
        stroke-linecap="round"
        stroke-linejoin="round"
    >
        <polyline points="5,7 9,11 13,7" />
        <line x1="9" y1="2" x2="9" y2="11" />
        <path d="M2 12v3a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-3" />
    </svg>
</template>
