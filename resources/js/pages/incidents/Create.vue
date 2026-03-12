<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';

defineProps<{
    incidentTypes: Record<string, unknown[]>;
    channels: { value: string; label?: string }[];
    priorities: { value: string; label?: string }[];
    priorityConfig: Record<string, unknown>;
}>();
</script>

<template>
    <AppLayout title="Create Incident">
        <div class="p-6">
            <h1 class="text-2xl font-bold">Create Incident</h1>
            <p class="mt-1 text-muted-foreground">
                Triage form. Full UI coming in Phase 2 Plan 2.
            </p>
        </div>
    </AppLayout>
</template>
