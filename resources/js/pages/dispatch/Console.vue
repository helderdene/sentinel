<script setup lang="ts">
defineProps<{
    incidents: Array<Record<string, unknown>>;
    units: Array<Record<string, unknown>>;
    agencies: Array<Record<string, unknown>>;
    metrics: {
        totalIncidents: number;
        activeIncidents: number;
        criticalIncidents: number;
        unitsAvailable: number;
        unitsTotal: number;
        averageHandleTime: number | null;
    };
}>();
</script>

<template>
    <div>
        <h1>Dispatch Console</h1>
    </div>
</template>
