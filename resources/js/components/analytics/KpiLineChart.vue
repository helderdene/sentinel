<script setup lang="ts">
import {
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';

import type { KpiTimeSeriesPoint } from '@/types/analytics';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Legend,
    Tooltip,
);

interface Dataset {
    label: string;
    data: KpiTimeSeriesPoint[];
    borderColor: string;
}

const props = defineProps<{
    datasets: Dataset[];
    visible: string[];
}>();

const METRIC_COLORS: Record<string, string> = {
    avg_response_time_min: '#2563eb',
    avg_scene_arrival_time_min: '#7c3aed',
    resolution_rate: '#16a34a',
    unit_utilization: '#d97706',
    false_alarm_rate: '#dc2626',
};

const chartData = computed(() => {
    const visibleDatasets = props.datasets.filter((ds) =>
        props.visible.includes(ds.label),
    );

    if (visibleDatasets.length === 0) {
        return { labels: [], datasets: [] };
    }

    // Use the longest dataset for labels
    const longestDs = visibleDatasets.reduce((a, b) =>
        a.data.length > b.data.length ? a : b,
    );

    const labels = longestDs.data.map((point) => {
        const d = new Date(point.date);

        return d.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
        });
    });

    return {
        labels,
        datasets: visibleDatasets.map((ds) => ({
            label: formatLabel(ds.label),
            data: ds.data.map((p) => p.value),
            borderColor: METRIC_COLORS[ds.label] ?? ds.borderColor,
            backgroundColor: 'transparent',
            borderWidth: 2,
            tension: 0.3,
            pointRadius: 2,
            pointHoverRadius: 5,
        })),
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: true,
            position: 'bottom' as const,
        },
        tooltip: {
            enabled: true,
            mode: 'index' as const,
            intersect: false,
        },
    },
    scales: {
        x: {
            display: true,
            grid: { display: false },
        },
        y: {
            display: true,
            beginAtZero: true,
        },
    },
    interaction: {
        mode: 'nearest' as const,
        axis: 'x' as const,
        intersect: false,
    },
};

function formatLabel(metric: string): string {
    const labels: Record<string, string> = {
        avg_response_time_min: 'Avg Response Time (min)',
        avg_scene_arrival_time_min: 'Avg Scene Arrival (min)',
        resolution_rate: 'Resolution Rate (%)',
        unit_utilization: 'Unit Utilization (%)',
        false_alarm_rate: 'False Alarm Rate (%)',
    };

    return labels[metric] ?? metric;
}
</script>

<template>
    <div class="h-[300px] w-full">
        <Line
            v-if="chartData.datasets.length > 0"
            :data="chartData"
            :options="chartOptions"
        />
        <div
            v-else
            class="flex h-full items-center justify-center text-sm text-neutral-400"
        >
            Select metrics to display
        </div>
    </div>
</template>
