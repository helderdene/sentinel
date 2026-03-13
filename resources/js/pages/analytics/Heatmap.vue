<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BarChart3, Download, FileDown, Map } from 'lucide-vue-next';
import { nextTick, onMounted, ref, watch } from 'vue';

import {
    dashboard,
    heatmap,
    reports,
} from '@/actions/App/Http/Controllers/AnalyticsController';
import ChoroplethLegend from '@/components/analytics/ChoroplethLegend.vue';
import FilterBar from '@/components/analytics/FilterBar.vue';
import { useAnalyticsFilters } from '@/composables/useAnalyticsFilters';
import { useAnalyticsMap } from '@/composables/useAnalyticsMap';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { BarangayDensity, FilterOptions } from '@/types/analytics';

const props = defineProps<{
    density: BarangayDensity[];
    geojson: GeoJSON.FeatureCollection;
    filters: Record<string, string>;
    filterOptions: FilterOptions;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Analytics', href: '/analytics' },
    { title: 'Heatmap', href: '/analytics/heatmap' },
];

const {
    preset,
    startDate,
    endDate,
    incidentTypeId,
    priority,
    barangayId,
    applyPreset,
    applyCustomDates,
    setIncidentType,
    setPriority,
    setBarangay,
    clearFilters,
} = useAnalyticsFilters();

const mapContainer = ref<HTMLElement | null>(null);

const { isLoaded, init, updateDensity, exportPng } = useAnalyticsMap(
    mapContainer,
    props.geojson,
    props.density,
);

onMounted(() => {
    nextTick(() => {
        init();
    });
});

// Watch for density prop changes (Inertia navigation with new filter data)
watch(
    () => props.density,
    (newDensity) => {
        if (isLoaded.value) {
            updateDensity(newDensity);
        }
    },
);
</script>

<template>
    <Head title="Incident Heatmap" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col">
            <!-- Tab Navigation -->
            <div
                class="flex items-center gap-1 border-b border-neutral-200 px-4 dark:border-neutral-800"
            >
                <Link
                    :href="dashboard.url()"
                    class="flex items-center gap-1.5 border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200"
                >
                    <BarChart3 class="h-4 w-4" />
                    Dashboard
                </Link>
                <Link
                    :href="heatmap.url()"
                    class="flex items-center gap-1.5 border-b-2 border-blue-600 px-3 py-2.5 text-sm font-medium text-blue-600 dark:text-blue-400"
                >
                    <Map class="h-4 w-4" />
                    Heatmap
                </Link>
                <Link
                    :href="reports.url()"
                    class="flex items-center gap-1.5 border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200"
                >
                    <FileDown class="h-4 w-4" />
                    Reports
                </Link>
            </div>

            <!-- Filter Bar -->
            <FilterBar
                v-model:preset="preset"
                v-model:start-date="startDate"
                v-model:end-date="endDate"
                v-model:incident-type-id="incidentTypeId"
                v-model:priority="priority"
                v-model:barangay-id="barangayId"
                :filter-options="filterOptions"
                @apply-preset="applyPreset"
                @apply-custom-dates="applyCustomDates"
                @set-incident-type="setIncidentType"
                @set-priority="setPriority"
                @set-barangay="setBarangay"
                @clear-filters="clearFilters"
            />

            <!-- Map Container -->
            <div class="relative flex-1">
                <div ref="mapContainer" class="absolute inset-0" />

                <ChoroplethLegend />

                <!-- Export Button -->
                <button
                    class="absolute top-4 right-4 z-10 inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white/90 px-3 py-2 text-xs font-medium text-neutral-700 shadow-sm backdrop-blur transition-colors hover:bg-white dark:border-neutral-700 dark:bg-zinc-900/90 dark:text-neutral-300 dark:hover:bg-zinc-800"
                    @click="exportPng()"
                >
                    <Download class="h-3.5 w-3.5" />
                    Export PNG
                </button>

                <!-- Loading Overlay -->
                <div
                    v-if="!isLoaded"
                    class="absolute inset-0 flex items-center justify-center bg-neutral-100 dark:bg-zinc-900"
                >
                    <div class="text-sm text-neutral-400">Loading map...</div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
