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
        <rect x="2" y="2" width="5" height="5" rx="0.5" />
        <rect x="9" y="2" width="5" height="3" rx="0.5" />
        <rect x="2" y="9" width="5" height="3" rx="0.5" />
        <rect x="9" y="7" width="5" height="5" rx="0.5" />
    </svg>
</template>
