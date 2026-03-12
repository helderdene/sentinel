<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { IncidentForQueue } from '@/types/incident';

defineProps<{
    incidents: IncidentForQueue[];
    channelCounts: Record<string, number>;
}>();
</script>

<template>
    <AppLayout title="Dispatch Queue">
        <div class="p-6">
            <h1 class="text-2xl font-bold">Dispatch Queue</h1>
            <p class="mt-1 text-muted-foreground">
                Priority-ordered incident queue. Full UI coming in Phase 2 Plan
                2.
            </p>
        </div>
    </AppLayout>
</template>
