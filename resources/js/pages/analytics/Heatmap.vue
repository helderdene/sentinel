<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface DensityData {
    barangay_id: number;
    name: string;
    incident_count: number;
}

interface FilterOption {
    id: number;
    name: string;
}

defineProps<{
    density: DensityData[];
    geojson: object;
    filters: Record<string, string>;
    filterOptions: {
        incident_types: FilterOption[];
        barangays: FilterOption[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Analytics', href: '/analytics' },
    { title: 'Heatmap', href: '/analytics/heatmap' },
];
</script>

<template>
    <Head title="Incident Heatmap" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <h1
                class="text-2xl font-semibold text-neutral-900 dark:text-neutral-100"
            >
                Incident Heatmap
            </h1>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">
                Choropleth map with barangay incident density -- full
                implementation in Plan 02.
            </p>
        </div>
    </AppLayout>
</template>
