<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { Incident } from '@/types/incident';

defineProps<{
    incident: Incident;
}>();
</script>

<template>
    <AppLayout title="Incident Detail">
        <div class="p-6">
            <h1 class="text-2xl font-bold">
                Incident {{ incident.incident_no }}
            </h1>
            <p class="mt-1 text-muted-foreground">
                Incident detail with timeline. Full UI coming in Phase 2 Plan 2.
            </p>
        </div>
    </AppLayout>
</template>
