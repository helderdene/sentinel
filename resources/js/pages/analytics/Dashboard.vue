<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BarChart3, FileDown, Map } from 'lucide-vue-next';
import { computed, ref } from 'vue';

import {
    dashboard,
    heatmap,
    reports,
} from '@/actions/App/Http/Controllers/AnalyticsController';
import FilterBar from '@/components/analytics/FilterBar.vue';
import KpiCard from '@/components/analytics/KpiCard.vue';
import KpiLineChart from '@/components/analytics/KpiLineChart.vue';
import { useAnalyticsFilters } from '@/composables/useAnalyticsFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type {
    FilterOptions,
    KpiMetrics,
    KpiTimeSeriesPoint,
} from '@/types/analytics';

const props = defineProps<{
    kpis: KpiMetrics;
    timeSeries: Record<string, KpiTimeSeriesPoint[]>;
    filters: Record<string, string>;
    filterOptions: FilterOptions;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Analytics', href: '/analytics' },
    { title: 'Dashboard', href: '/analytics/dashboard' },
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

// Metric display configuration
const METRICS = [
    {
        key: 'avg_response_time_min',
        title: 'Avg Response Time',
        unit: 'min',
        color: 'blue',
    },
    {
        key: 'avg_scene_arrival_time_min',
        title: 'Avg Scene Arrival',
        unit: 'min',
        color: 'purple',
    },
    {
        key: 'resolution_rate',
        title: 'Resolution Rate',
        unit: '%',
        color: 'green',
    },
    {
        key: 'unit_utilization',
        title: 'Unit Utilization',
        unit: '%',
        color: 'amber',
    },
    {
        key: 'false_alarm_rate',
        title: 'False Alarm Rate',
        unit: '%',
        color: 'red',
    },
] as const;

function formatValue(key: string, value: number | null): string {
    if (value === null) {
        return '--';
    }

    if (
        key === 'avg_response_time_min' ||
        key === 'avg_scene_arrival_time_min'
    ) {
        return value.toFixed(1);
    }

    return value.toFixed(1);
}

function computeTrend(key: string): number {
    const series = props.timeSeries[key];

    if (!series || series.length < 2) {
        return 0;
    }

    const midpoint = Math.floor(series.length / 2);
    const firstHalf = series.slice(0, midpoint);
    const secondHalf = series.slice(midpoint);

    if (firstHalf.length === 0 || secondHalf.length === 0) {
        return 0;
    }

    const avgFirst =
        firstHalf.reduce((sum, p) => sum + p.value, 0) / firstHalf.length;
    const avgSecond =
        secondHalf.reduce((sum, p) => sum + p.value, 0) / secondHalf.length;

    if (avgFirst === 0) {
        return 0;
    }

    return ((avgSecond - avgFirst) / avgFirst) * 100;
}

function getSparklineData(key: string): number[] {
    const series = props.timeSeries[key];

    if (!series) {
        return [];
    }

    return series.map((p) => p.value);
}

// Line chart visibility toggle
const visibleMetrics = ref<string[]>([
    'avg_response_time_min',
    'resolution_rate',
]);

const chartDatasets = computed(() =>
    METRICS.map((m) => ({
        label: m.key,
        data: props.timeSeries[m.key] ?? [],
        borderColor: '',
    })),
);

function toggleMetric(key: string): void {
    const idx = visibleMetrics.value.indexOf(key);

    if (idx >= 0) {
        visibleMetrics.value.splice(idx, 1);
    } else {
        visibleMetrics.value.push(key);
    }
}

const METRIC_COLORS: Record<string, string> = {
    avg_response_time_min: '#378ADD',
    avg_scene_arrival_time_min: '#7c3aed',
    resolution_rate: '#1D9E75',
    unit_utilization: '#EF9F27',
    false_alarm_rate: '#E24B4A',
};
</script>

<template>
    <Head title="Analytics Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col">
            <!-- Tab Navigation -->
            <div class="flex items-center gap-1 border-b border-border px-4">
                <Link
                    :href="dashboard.url()"
                    class="flex items-center gap-1.5 border-b-2 border-t-accent px-3 py-2.5 text-sm font-medium text-t-accent"
                >
                    <BarChart3 class="h-4 w-4" />
                    Dashboard
                </Link>
                <Link
                    :href="heatmap.url()"
                    class="flex items-center gap-1.5 border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-t-text-dim hover:text-foreground"
                >
                    <Map class="h-4 w-4" />
                    Heatmap
                </Link>
                <Link
                    :href="reports.url()"
                    class="flex items-center gap-1.5 border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-t-text-dim hover:text-foreground"
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

            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-4">
                <!-- KPI Cards -->
                <div
                    class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3 xl:grid-cols-5"
                >
                    <template v-if="kpis">
                        <KpiCard
                            v-for="m in METRICS"
                            :key="m.key"
                            :title="m.title"
                            :value="
                                formatValue(
                                    m.key,
                                    kpis[m.key as keyof KpiMetrics],
                                )
                            "
                            :unit="m.unit"
                            :trend="computeTrend(m.key)"
                            :sparkline-data="getSparklineData(m.key)"
                            :color="m.color"
                        />
                    </template>
                    <template v-else>
                        <div
                            v-for="i in 5"
                            :key="i"
                            class="h-28 animate-pulse rounded-[var(--radius)] bg-secondary"
                        />
                    </template>
                </div>

                <!-- Metric Toggle + Line Chart -->
                <div
                    class="rounded-[var(--radius)] border border-border bg-card p-4 shadow-[var(--shadow-1)]"
                >
                    <div class="mb-4 flex flex-wrap gap-2">
                        <label
                            v-for="m in METRICS"
                            :key="m.key"
                            class="flex cursor-pointer items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium transition-colors"
                            :class="
                                visibleMetrics.includes(m.key)
                                    ? 'bg-secondary text-foreground'
                                    : 'text-t-text-faint'
                            "
                        >
                            <input
                                type="checkbox"
                                class="sr-only"
                                :checked="visibleMetrics.includes(m.key)"
                                @change="toggleMetric(m.key)"
                            />
                            <span
                                class="inline-block h-2.5 w-2.5 rounded-full"
                                :style="{
                                    backgroundColor: METRIC_COLORS[m.key],
                                }"
                            />
                            {{ m.title }}
                        </label>
                    </div>

                    <KpiLineChart
                        :datasets="chartDatasets"
                        :visible="visibleMetrics"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
