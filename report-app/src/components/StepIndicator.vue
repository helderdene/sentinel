<script setup lang="ts">
const props = withDefaults(
    defineProps<{
        currentStep: number;
        totalSteps?: number;
    }>(),
    {
        totalSteps: 3,
    }
);
</script>

<template>
    <div class="flex items-center gap-2">
        <div
            v-for="step in props.totalSteps"
            :key="step"
            class="h-1 flex-1 rounded-full transition-colors duration-300"
            :style="{
                backgroundColor:
                    step <= props.currentStep
                        ? 'var(--t-accent)'
                        : 'var(--t-border)',
            }"
        />
    </div>
</template>
