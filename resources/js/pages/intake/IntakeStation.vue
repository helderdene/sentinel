<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps<{
    incidentTypes: Record<string, unknown[]>;
    channels: { value: string; label?: string }[];
    priorities: { value: string; label?: string }[];
    pendingIncidents: unknown[];
    triagedIncidents: unknown[];
    priorityConfig?: Record<string, unknown>;
}>();
</script>

<template>
    <div>
        <h1 class="text-2xl font-bold">Intake Station</h1>
        <p class="mt-1 text-muted-foreground">
            Full intake UI coming in Plans 02-04.
        </p>
    </div>
</template>
