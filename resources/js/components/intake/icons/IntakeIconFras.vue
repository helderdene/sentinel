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
        viewBox="0 0 16 16"
        fill="none"
        :stroke="color"
        stroke-width="1.3"
        stroke-linecap="round"
        stroke-linejoin="round"
    >
        <!-- Reticle corners (face recognition targeting brackets) -->
        <path d="M2 4V2H4" />
        <path d="M12 2H14V4" />
        <path d="M14 12V14H12" />
        <path d="M4 14H2V12" />
        <!-- Face outline -->
        <circle cx="8" cy="8" r="3.5" />
        <!-- Eye dots -->
        <circle cx="6.8" cy="7.3" r="0.4" :fill="color" stroke="none" />
        <circle cx="9.2" cy="7.3" r="0.4" :fill="color" stroke="none" />
        <!-- Smile arc -->
        <path d="M6.5 9.2C7 9.7 7.5 10 8 10C8.5 10 9 9.7 9.5 9.2" />
    </svg>
</template>
