<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface KpiData {
    avg_response_time_min: number | null;
    avg_scene_arrival_time_min: number | null;
    resolution_rate: number;
    unit_utilization: number;
    false_alarm_rate: number;
}

interface TimeSeriesPoint {
    date: string;
    value: number;
}

interface FilterOption {
    id: number;
    name: string;
}

defineProps<{
    kpis: KpiData;
    timeSeries: Record<string, TimeSeriesPoint[]>;
    filters: Record<string, string>;
    filterOptions: {
        incident_types: FilterOption[];
        barangays: FilterOption[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Analytics', href: '/analytics' },
    { title: 'Dashboard', href: '/analytics/dashboard' },
];
</script>

<template>
    <Head title="Analytics Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <h1
                class="text-2xl font-semibold text-neutral-900 dark:text-neutral-100"
            >
                KPI Dashboard
            </h1>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">
                Analytics dashboard with KPI metrics -- full implementation in
                Plan 02.
            </p>
        </div>
    </AppLayout>
</template>
