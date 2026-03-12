<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';

defineProps<{
    incidents: {
        data: unknown[];
        next_cursor: string | null;
        prev_cursor: string | null;
    };
}>();
</script>

<template>
    <AppLayout title="Incidents">
        <div class="p-6">
            <h1 class="text-2xl font-bold">All Incidents</h1>
            <p class="mt-1 text-muted-foreground">
                Incident list with status filters. Full UI coming in Phase 2
                Plan 2.
            </p>
        </div>
    </AppLayout>
</template>
